<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class OrderTrackingController extends Controller
{
    public function show(Request $request, string $orderNumber): SymfonyResponse
    {
        $token = (string) $request->query('token', '');
        $appKey = (string) config('app.key');
        abort_if($token === '' || $appKey === '', 404);

        $expectedToken = hash_hmac('sha256', 'order-tracking|'.$orderNumber, $appKey);
        abort_unless(hash_equals($expectedToken, $token), 404);

        $order = Order::query()
            ->with(['store:id,name,currency', 'statusHistories' => fn ($query) => $query
                ->orderBy('created_at')->orderBy('id')])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $response = Inertia::render('storefront/order-tracking', [
            'store' => [
                'name' => $order->store->name,
                'currency' => $order->currency,
            ],
            'order' => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'placed_at' => $order->placed_at?->toISOString(),
                'shipped_at' => $order->shipped_at?->toISOString(),
                'delivered_at' => $order->delivered_at?->toISOString(),
                'shipping_courier' => $order->shipping_courier,
                'shipping_service' => $order->shipping_service,
                'tracking_number' => $order->shipping_tracking_number,
                'tracking_url' => $order->shipping_tracking_url,
                'destination' => $order->shipping_destination_label,
                'history' => $order->statusHistories->map(fn ($history): array => [
                    'status' => $history->to_status,
                    'note' => $history->note,
                    'created_at' => $history->created_at?->toISOString(),
                ]),
            ],
        ]);

        $response = $response->toResponse($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
