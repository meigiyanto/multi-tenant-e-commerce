<?php

namespace App\Services\Notifications;

use App\Jobs\SendOrderPaidEmail;
use App\Jobs\SendOrderPaidWhatsApp;
use App\Models\NotificationDelivery;
use App\Models\Order;
use Illuminate\Support\Str;

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
                SendOrderPaidEmail::dispatch($emailDelivery->id);
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
                SendOrderPaidWhatsApp::dispatch($whatsAppDelivery->id);
            }
        }

        return $emailDelivery ?? $whatsAppDelivery ?? null;
    }
}
