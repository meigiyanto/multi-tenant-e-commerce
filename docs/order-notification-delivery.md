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

## Backfill notifications for historical paid orders

Orders paid before notification tracking was deployed are not automatically replayed by Midtrans. Inspect eligible historical orders first; the command is a dry run unless `--dispatch` is explicitly supplied:

```bash
php artisan notifications:backfill-paid-orders
```

To limit the inspection or processing to one order ID:

```bash
php artisan notifications:backfill-paid-orders --order=123
```

After reviewing the dry-run output, use `--dispatch` to create missing delivery records and queue the email/WhatsApp jobs:

```bash
php artisan notifications:backfill-paid-orders --dispatch
php artisan notifications:backfill-paid-orders --dispatch --order=123
```

The command only selects orders whose order payment status and at least one payment transaction are both `paid`. It skips channels that already have a `payment_confirmed` delivery record, so repeated runs do not enqueue duplicates. Configure an asynchronous queue connection (for example `QUEUE_CONNECTION=database`) and run a queue worker before dispatching; with the `sync` queue driver, jobs may execute immediately inside the command. Review recipients and test delivery before processing all historical orders.

## Inspect deliveries

Use Tinker:

```php
$order = App\\Models\\Order::with('notificationDeliveries')->where('order_number', 'ORDER-NUMBER')->firstOrFail();
$order->notificationDeliveries->map->only([
    'channel', 'recipient', 'status', 'provider', 'provider_message_id',
    'queued_at', 'sent_at', 'delivered_at', 'read_at', 'failed_at', 'last_error',
]);
```

## Fonnte WhatsApp

The payment confirmation event also queues one WhatsApp delivery when the order has a customer phone number. Configure these values in the application environment:

```dotenv
FONNTE_TOKEN=your_device_token
FONNTE_WEBHOOK_SECRET=use-a-long-random-secret
```

Never commit either value. The queued job calls Fonnte's `POST https://api.fonnte.com/send` endpoint using the token in the Authorization header. Fonnte's successful API response is recorded as `sent`; it is not proof of delivery or that the customer read the message.

For delivery updates, configure Fonnte's **Webhook Update Message Status** URL to:

```
https://your-public-domain.example/webhooks/fonnte/YOUR_FONNTE_WEBHOOK_SECRET
```

Use the same secret as `FONNTE_WEBHOOK_SECRET`. The webhook stores the raw Fonnte status/state payload and maps recognizable delivered/read/failed states to the delivery timeline. Fonnte must be able to reach this public HTTPS URL. Do not use localhost.

After changing environment values, run:

```bash
php artisan config:clear
php artisan queue:work --tries=3
```

The WhatsApp channel is independently deduplicated by `order_id + event + channel`. Only test with a number you control before enabling customer notifications.
