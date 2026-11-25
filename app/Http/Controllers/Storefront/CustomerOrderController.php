<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Stores\StoreContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerOrderController extends Controller
{
    public function index(Request $request, StoreContext $context): Response
    {
        $store = $context->store();
        abort_if($store === null, 404);

        $orderRecords = Order::query()
            ->where('store_id', $store->id)
            ->where('customer_id', $request->user('customer')->id)
            ->with('store:id,name')
            ->withCount('items')
            ->latest('placed_at')
            ->latest('id')
            ->get();
        $orders = $orderRecords->map(fn (Order $order): array => [
            'order_number' => $order->order_number,
            'store_name' => $order->store->name,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'items_count' => $order->items_count,
            'total' => $order->total,
            'currency' => $order->currency,
            'placed_at' => $order->placed_at?->toISOString(),
            'tracking_url' => route('storefront.orders.track', [
                'orderNumber' => $order->order_number,
                'token' => hash_hmac('sha256', 'order-tracking|'.$order->order_number, (string) config('app.key')),
            ]),
        ]);

        return Inertia::render('storefront/account/orders', [
            'store' => ['name' => $store->name, 'currency' => $store->currency],
            'orders' => $orders,
        ]);
    }
}
