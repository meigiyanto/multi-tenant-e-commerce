<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Team;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StoreDashboardController extends Controller
{
    public function __invoke(Team $team, Store $store): Response
    {
        abort_unless((int) $store->team_id === (int) $team->id, 404);
        Gate::authorize('update', $store);

        $orders = Order::query()->where('store_id', $store->id);

        $paidRevenue = (string) ((clone $orders)->where('payment_status', 'paid')->sum('total'));
        [$revenueWhole, $revenueFraction] = array_pad(explode('.', $paidRevenue, 2), 2, '');
        $paidRevenue = $revenueWhole.'.'.str_pad(substr($revenueFraction, 0, 2), 2, '0');

        $stats = [
            'total_orders' => (clone $orders)->count(),
            'pending_orders' => (clone $orders)->where('status', 'pending')->count(),
            'paid_orders' => (clone $orders)->where('payment_status', 'paid')->count(),
            'paid_revenue' => $paidRevenue,
            'active_products' => Product::query()
                ->where('store_id', $store->id)
                ->where('status', 'active')
                ->count(),
        ];

        $recentOrderRecords = (clone $orders)
            ->withCount('items')
            ->orderByDesc('placed_at')
            ->limit(8)
            ->get();
        $recentOrders = $recentOrderRecords->map(fn (Order $order): array => [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'total' => (string) $order->total,
            'currency' => $order->currency,
            'items_count' => (int) $order->items_count,
            'placed_at' => $order->placed_at?->toISOString(),
        ]);

        return Inertia::render('stores/dashboard', [
            'team' => ['id' => $team->id, 'name' => $team->name, 'slug' => $team->slug],
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'currency' => $store->currency,
            ],
            'stats' => $stats,
            'recentOrders' => $recentOrders,
        ]);
    }
}
