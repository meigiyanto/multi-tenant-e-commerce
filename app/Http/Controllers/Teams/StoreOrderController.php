<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Models\Team;
use App\Services\Notifications\OrderNotificationService;
use App\Services\Stores\StoreOrderStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StoreOrderController extends Controller
{
    private const STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

    public function index(Request $request, Team $team, Store $store): Response
    {
        $this->authorizeStore($team, $store);

        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(self::STATUSES)],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $orders = Order::query()
            ->where('store_id', $store->id)
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('order_number', 'like', '%'.$search.'%')
                        ->orWhere('customer_name', 'like', '%'.$search.'%')
                        ->orWhere('customer_email', 'like', '%'.$search.'%');
                });
            })
            ->withCount('items')
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total' => (string) $order->total,
                'currency' => $order->currency,
                'items_count' => (int) $order->items_count,
                'placed_at' => $order->placed_at?->toISOString(),
            ]);

        return Inertia::render('stores/orders/index', [
            'team' => ['name' => $team->name, 'slug' => $team->slug],
            'store' => ['id' => $store->id, 'name' => $store->name, 'currency' => $store->currency],
            'orders' => $orders,
            'filters' => [
                'status' => $validated['status'] ?? '',
                'search' => $validated['search'] ?? '',
            ],
            'statuses' => self::STATUSES,
        ]);
    }

    public function show(Team $team, Store $store, Order $order): Response
    {
        $this->authorizeOrder($team, $store, $order);

        $order->load([
            'items',
            'statusHistories' => fn ($query) => $query->with('user:id,name')->orderByDesc('created_at')->orderByDesc('id'),
            'notificationDeliveries' => fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id'),
            'paymentTransactions' => fn ($query) => $query->select([
                'id', 'order_id', 'gateway', 'status', 'amount', 'currency', 'created_at',
            ])->orderByDesc('created_at'),
        ]);

        return Inertia::render('stores/orders/show', [
            'team' => ['name' => $team->name, 'slug' => $team->slug],
            'store' => ['id' => $store->id, 'name' => $store->name, 'currency' => $store->currency],
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'customer' => [
                    'name' => $order->customer_name,
                    'email' => $order->customer_email,
                    'phone' => $order->customer_phone,
                ],
                'shipping' => [
                    'address' => $order->shipping_address,
                    'city' => $order->shipping_city,
                    'province' => $order->shipping_province,
                    'postal_code' => $order->shipping_postal_code,
                    'destination_label' => $order->shipping_destination_label,
                    'provider' => $order->shipping_provider,
                    'courier' => $order->shipping_courier,
                    'service' => $order->shipping_service,
                    'tracking_number' => $order->shipping_tracking_number,
                    'customer_tracking_url' => route('storefront.orders.track', ['orderNumber' => $order->order_number, 'token' => hash_hmac('sha256', 'order-tracking|'.$order->order_number, (string) config('app.key'))]),
                    'tracking_url' => $order->shipping_tracking_url,
                    'shipped_at' => $order->shipped_at?->toISOString(),
                    'delivered_at' => $order->delivered_at?->toISOString(),
                ],
                'customer_note' => $order->customer_note,
                'currency' => $order->currency,
                'subtotal' => (string) $order->subtotal,
                'shipping_amount' => (string) $order->shipping_amount,
                'discount_amount' => (string) $order->discount_amount,
                'total' => (string) $order->total,
                'placed_at' => $order->placed_at?->toISOString(),
                'items' => $order->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'variant_name' => $item->variant_name,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'line_total' => (string) $item->line_total,
                ]),
                'notifications' => $order->notificationDeliveries->map(fn ($delivery): array => [
                    'id' => $delivery->id,
                    'channel' => $delivery->channel,
                    'recipient' => $delivery->recipient,
                    'status' => $delivery->status,
                    'provider' => $delivery->provider,
                    'provider_message_id' => $delivery->provider_message_id,
                    'last_error' => $delivery->last_error,
                    'retryable' => data_get($delivery->metadata, 'dispatch_error') !== null || data_get($delivery->metadata, 'job_failure') !== null,
                    'queued_at' => $delivery->queued_at?->toISOString(),
                    'sent_at' => $delivery->sent_at?->toISOString(),
                    'delivered_at' => $delivery->delivered_at?->toISOString(),
                    'read_at' => $delivery->read_at?->toISOString(),
                    'failed_at' => $delivery->failed_at?->toISOString(),
                ]),
                'payments' => $order->paymentTransactions->map(fn ($payment): array => [
                    'id' => $payment->id,
                    'gateway' => $payment->gateway,
                    'status' => $payment->status,
                    'amount' => (string) $payment->amount,
                    'currency' => $payment->currency,
                    'created_at' => $payment->created_at?->toISOString(),
                ]),
                'history' => $order->statusHistories->map(fn ($history): array => [
                    'id' => $history->id,
                    'from_status' => $history->from_status,
                    'to_status' => $history->to_status,
                    'note' => $history->note,
                    'actor' => $history->user?->name,
                    'created_at' => $history->created_at?->toISOString(),
                ]),
                'next_status' => match (true) {
                    $order->payment_status === 'paid' && $order->status === 'processing' => 'shipped',
                    $order->payment_status === 'paid' && $order->status === 'shipped' => 'delivered',
                    default => null,
                },
            ],
        ]);
    }

    public function updateStatus(
        Request $request,
        Team $team,
        Store $store,
        Order $order,
        StoreOrderStatusService $statusService,
        OrderNotificationService $notificationService,
    ): RedirectResponse {
        $this->authorizeOrder($team, $store, $order);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['shipped', 'delivered'])],
            'note' => ['required_if:status,delivered', 'nullable', 'string', 'max:1000'],
            'tracking_number' => ['required_if:status,shipped', 'nullable', 'string', 'max:100'],
            'tracking_url' => ['nullable', 'url', 'regex:/^https?:\/\//i', 'max:2048'],
        ]);

        $statusService->transition(
            $store,
            $order,
            $request->user('web'),
            $validated['status'],
            $validated['note'] ?? null,
            $validated['tracking_number'] ?? null,
            $validated['tracking_url'] ?? null,
        );
        $notificationService->queueOrderStatusUpdated((int) $order->id, $validated['status']);

        return to_route('teams.stores.orders.show', [
            'team' => $team->slug,
            'store' => $store->id,
            'order' => $order->id,
        ])->with('success', __('Order status updated.'));
    }

    public function retryNotifications(Team $team, Store $store, Order $order, OrderNotificationService $notificationService): RedirectResponse
    {
        $this->authorizeOrder($team, $store, $order);

        abort_unless($order->payment_status === 'paid', 422, 'Only paid orders can retry order notifications.');

        $hasRetryableDelivery = $order->notificationDeliveries()
            ->where(function ($query): void {
                $query->whereNotNull('metadata->dispatch_error')
                    ->orWhereNotNull('metadata->job_failure');
            })
            ->exists();

        abort_unless($hasRetryableDelivery, 409, 'No retryable notification failures were found.');

        $notificationService->retryFailedNotifications((int) $order->id);

        return to_route('teams.stores.orders.show', [
            'team' => $team->slug,
            'store' => $store->id,
            'order' => $order->id,
        ])->with('success', __('Retry queued for failed order notifications.'));
    }

    private function authorizeStore(Team $team, Store $store): void
    {
        abort_unless((int) $store->team_id === (int) $team->id, 404);
        Gate::authorize('update', $store);
    }

    private function authorizeOrder(Team $team, Store $store, Order $order): void
    {
        $this->authorizeStore($team, $store);
        abort_unless((int) $order->store_id === (int) $store->id, 404);
    }
}
