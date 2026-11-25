<?php

namespace App\Services\Notifications;

use App\Jobs\SendOrderPaidEmail;
use App\Jobs\SendOrderPaidWhatsApp;
use App\Models\NotificationDelivery;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class OrderNotificationService
{
    public function queuePaymentConfirmed(int $orderId): ?NotificationDelivery
    {
        $order = Order::query()->with('store')->findOrFail($orderId);

        $emailDelivery = null;

        if (filter_var($order->customer_email, FILTER_VALIDATE_EMAIL)) {
            $emailDelivery = NotificationDelivery::query()->firstOrCreate(
                [
                    'order_id' => $order->id,
                    'event' => 'payment_confirmed',
                    'channel' => 'mail',
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'store_id' => $order->store_id,
                    'recipient' => $order->customer_email,
                    'status' => 'queued',
                    'provider' => config('mail.default'),
                    'queued_at' => now(),
                ],
            );

            if ($emailDelivery->wasRecentlyCreated || (data_get($emailDelivery->metadata, 'dispatch_error') !== null || data_get($emailDelivery->metadata, 'job_failure') !== null)) {
                $this->dispatchNotification(new SendOrderPaidEmail($emailDelivery->id), $emailDelivery);
            }
        }

        $whatsAppDelivery = null;
        $phone = preg_replace('/\\D+/', '', (string) $order->customer_phone);
        if ($phone !== '') {
            $whatsAppDelivery = NotificationDelivery::query()->firstOrCreate(
                [
                    'order_id' => $order->id,
                    'event' => 'payment_confirmed',
                    'channel' => 'whatsapp',
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'store_id' => $order->store_id,
                    'recipient' => $phone,
                    'status' => 'queued',
                    'provider' => 'fonnte',
                    'queued_at' => now(),
                ],
            );

            if ($whatsAppDelivery->wasRecentlyCreated || (data_get($whatsAppDelivery->metadata, 'dispatch_error') !== null || data_get($whatsAppDelivery->metadata, 'job_failure') !== null)) {
                $this->dispatchNotification(new SendOrderPaidWhatsApp($whatsAppDelivery->id), $whatsAppDelivery);
            }
        }

        return $emailDelivery ?? $whatsAppDelivery ?? null;
    }

    public function queueOrderStatusUpdated(int $orderId, string $status): void
    {
        if (! in_array($status, ['shipped', 'delivered'], true)) {
            return;
        }

        $order = Order::query()->findOrFail($orderId);
        $event = $status === 'shipped' ? 'order_shipped' : 'order_delivered';
        $recipients = [
            ['channel' => 'mail', 'recipient' => $order->customer_email, 'provider' => config('mail.default')],
            ['channel' => 'whatsapp', 'recipient' => preg_replace('/\D+/', '', (string) $order->customer_phone), 'provider' => 'fonnte'],
        ];

        foreach ($recipients as $recipient) {
            if ($recipient['recipient'] === '' || ($recipient['channel'] === 'mail' && ! filter_var($recipient['recipient'], FILTER_VALIDATE_EMAIL))) {
                continue;
            }

            $delivery = NotificationDelivery::query()->firstOrCreate(
                ['order_id' => $order->id, 'event' => $event, 'channel' => $recipient['channel']],
                [
                    'uuid' => (string) Str::uuid(),
                    'store_id' => $order->store_id,
                    'recipient' => $recipient['recipient'],
                    'status' => 'queued',
                    'provider' => $recipient['provider'],
                    'queued_at' => now(),
                ],
            );

            if ($delivery->wasRecentlyCreated) {
                $job = $recipient['channel'] === 'mail'
                    ? new SendOrderPaidEmail($delivery->id)
                    : new SendOrderPaidWhatsApp($delivery->id);
                $this->dispatchNotification($job, $delivery);
            }
        }
    }

    public function retryFailedNotifications(int $orderId): int
    {
        $deliveries = NotificationDelivery::query()
            ->where('order_id', $orderId)
            ->where(function ($query): void {
                $query->whereNotNull('metadata->dispatch_error')
                    ->orWhereNotNull('metadata->job_failure');
            })
            ->get();

        foreach ($deliveries as $delivery) {
            $metadata = (array) $delivery->metadata;
            $metadata = array_diff_key($metadata, ['dispatch_error' => true, 'job_failure' => true]);
            $delivery->update([
                'status' => 'queued',
                'failed_at' => null,
                'last_error' => null,
                'metadata' => $metadata,
            ]);

            $job = $delivery->channel === 'mail'
                ? new SendOrderPaidEmail($delivery->id)
                : new SendOrderPaidWhatsApp($delivery->id);
            $this->dispatchNotification($job, $delivery);
        }

        return $deliveries->count();
    }

    private function dispatchNotification(object $job, NotificationDelivery $delivery): void
    {
        try {
            dispatch($job);
        } catch (Throwable $exception) {
            $metadata = (array) $delivery->fresh()->metadata;
            $metadata['dispatch_error'] = [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'occurred_at' => now()->toISOString(),
            ];

            $delivery->update([
                'status' => 'queued',
                'failed_at' => null,
                'last_error' => $exception->getMessage(),
                'metadata' => $metadata,
            ]);

            Log::error('Order notification dispatch failed.', [
                'notification_delivery_id' => $delivery->id,
                'order_id' => $delivery->order_id,
                'channel' => $delivery->channel,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        $metadata = (array) $delivery->fresh()->metadata;
        $metadata = array_diff_key($metadata, ['dispatch_error' => true]);
        $delivery->update(['metadata' => $metadata]);
    }
}
