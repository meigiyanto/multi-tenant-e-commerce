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

        $orders = Order::query()
            ->where('store_id', $store->id)
            ->where('user_id', $request->user()->id);

        $latestOrders = (clone $orders)
            ->withCount('items')
            ->latest('placed_at')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Order $order): array => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total' => $order->total,
                'currency' => $order->currency,
                'placed_at' => $order->placed_at?->toISOString(),
                'items_count' => $order->items_count,
            ]);

        $user = $request->user();
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
