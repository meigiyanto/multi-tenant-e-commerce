# Order notification delivery tracking

When Midtrans confirms an order payment, the payment service creates one `notification_deliveries` row for the `payment_confirmed` email event and queues `SendOrderPaidEmail`. A unique key on `order_id + event + channel` prevents duplicate customer emails when Midtrans retries its webhook or payment reconciliation runs again.

## Email queue

Configure a real Laravel mail transport in `.env` (`MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`) and use a persistent queue such as `QUEUE_CONNECTION=database`. Run the queue worker:

```bash
php artisan queue:work --tries=3
```

The `sent` state means the configured mail transport accepted the message. It is not proof that the recipient's mailbox received or opened it.

## Delivery provider webhook

A provider that supports delivery events can call:

```
POST /webhooks/notification-deliveries/{uuid}
Content-Type: application/json
X-Delivery-Signature: <hex HMAC-SHA256 of the exact raw request body>
```

Example body:

```json
{
  "status": "delivered",
  "provider_message_id": "provider-message-id",
  "occurred_at": "2026-11-23T12:00:00+07:00",
  "metadata": {}
}
```

Allowed status callbacks are `delivered`, `read`, and `failed`. The webhook is authenticated using `NOTIFICATION_DELIVERY_WEBHOOK_SECRET`. Configure the same secret in the provider-side webhook adapter. Duplicate callbacks are safe; invalid state transitions are rejected with HTTP 409.

The generic callback contract does not automatically configure a provider. Actual delivered/read events require a mail provider that offers delivery webhooks and a small provider-specific adapter that maps its event format to this contract.

## Inspect deliveries

Use Tinker:

```php
$order = App\\Models\\Order::with('notificationDeliveries')->where('order_number', 'ORDER-NUMBER')->firstOrFail();
$order->notificationDeliveries->map->only([
    'channel', 'recipient', 'status', 'provider', 'provider_message_id',
    'queued_at', 'sent_at', 'delivered_at', 'read_at', 'failed_at', 'last_error',
]);
```

WhatsApp is intentionally not sent until a provider (for example, Meta WhatsApp Cloud API or another BSP) and its credentials are selected. The existing delivery table and signed callback contract support adding that channel without treating an API acceptance as delivery.
