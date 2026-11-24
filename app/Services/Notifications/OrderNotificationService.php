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

            if ($emailDelivery->wasRecentlyCreated) {
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

            if ($whatsAppDelivery->wasRecentlyCreated) {
                $this->dispatchNotification(new SendOrderPaidWhatsApp($whatsAppDelivery->id), $whatsAppDelivery);
            }
        }

        return $emailDelivery ?? $whatsAppDelivery ?? null;
    }

    private function dispatchNotification(object $job, NotificationDelivery $delivery): void
    {
        try {
            dispatch($job);
        } catch (Throwable $exception) {
            $delivery->update([
                'status' => 'failed',
                'failed_at' => now(),
                'last_error' => $exception->getMessage(),
            ]);

            Log::error('Order payment notification dispatch failed.', [
                'notification_delivery_id' => $delivery->id,
                'order_id' => $delivery->order_id,
                'channel' => $delivery->channel,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
