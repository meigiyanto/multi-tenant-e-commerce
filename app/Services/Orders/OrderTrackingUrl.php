<?php

namespace App\Services\Orders;

use App\Models\Order;

class OrderTrackingUrl
{
    public function for(Order $order): string
    {
        $token = hash_hmac('sha256', 'order-tracking|'.$order->order_number, (string) config('app.key'));

        return route('storefront.orders.track', [
            'orderNumber' => $order->order_number,
            'token' => $token,
        ]);
    }
}
