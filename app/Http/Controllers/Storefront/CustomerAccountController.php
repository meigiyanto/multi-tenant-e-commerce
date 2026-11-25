<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Stores\StoreContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAccountController extends Controller
{
    public function index(Request $request, StoreContext $context): Response
    {
        $store = $context->store();
        abort_if($store === null, 404);

        $customer = $request->user('customer');
        $orders = Order::query()->where('store_id', $store->id)->where('customer_id', $customer->id);

        $latestOrders = (clone $orders)
            ->with(['store:id,name'])
            ->withCount('items')
            ->latest('placed_at')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Order $order): array => [
                'order_number' => $order->order_number,
                'store_name' => $order->store?->name ?? 'Deleted store',
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total' => $order->total,
                'currency' => $order->currency,
                'placed_at' => $order->placed_at?->toISOString(),
                'items_count' => $order->items_count,
            ]);

        $user = $customer;
        $profileComplete = filled($user->name) && filled($user->email)
            && filled($user->phone) && filled($user->shipping_address)
            && filled($user->shipping_city) && filled($user->shipping_province)
            && filled($user->shipping_postal_code);

        return Inertia::render('storefront/account/index', [
            'store' => ['name' => $store->name, 'currency' => $store->currency],
            'summary' => [
                'orders_count' => (clone $orders)->count(),
                'profile_complete' => $profileComplete,
            ],
            'latest_orders' => $latestOrders,
        ]);
    }
}
