<?php

namespace App\Services\Notifications;

use App\Jobs\SendOrderPaidEmail;
use App\Models\NotificationDelivery;
use App\Models\Order;
use Illuminate\Support\Str;

class OrderNotificationService
{
    public function queuePaymentConfirmed(int $orderId): ?NotificationDelivery
    {
        $order = Order::query()->with('store')->findOrFail($orderId);

        if (! filter_var($order->customer_email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $delivery = NotificationDelivery::query()->firstOrCreate(
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

        if ($delivery->wasRecentlyCreated) {
            SendOrderPaidEmail::dispatch($delivery->id)->afterCommit();
        }

        return $delivery;
    }
}
