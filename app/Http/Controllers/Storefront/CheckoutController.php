<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Storefront\CartService;
use App\Services\Storefront\CheckoutService;
use App\Support\Stores\StoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CheckoutService $checkout,
    ) {}

    public function show(Request $request, StoreContext $context): Response
    {
        $store = $context->store();
        abort_if($store === null, 404);

        $cart = $this->carts->findCurrent($store, $request->session()->getId());
        abort_if($cart === null || $cart->items()->doesntExist(), 404);

        $items = $this->carts->items($cart)->get()->map(fn ($item): array => [
            'id' => $item->id,
            'name' => $item->product->name,
            'variant_name' => $item->variant->name,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'line_total' => number_format((float) $item->unit_price * $item->quantity, 2, '.', ''),
            'image_url' => ($image = $item->product->images->firstWhere('is_primary', true) ?? $item->product->images->first())
                ? Storage::disk('public')->url($image->path)
                : null,
        ]);

        return Inertia::render('storefront/checkout', [
            'store' => ['name' => $store->name, 'currency' => $store->currency],
            'items' => $items,
            'subtotal' => $this->carts->subtotal($cart),
            'customer' => [
                'name' => auth()->user()?->name ?? '',
                'email' => auth()->user()?->email ?? '',
                'phone' => '',
            ],
        ]);
    }

    public function store(Request $request, StoreContext $context): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_email' => ['required', 'email', 'max:180'],
            'customer_phone' => ['required', 'string', 'max:40'],
            'shipping_address' => ['required', 'string', 'max:2000'],
            'shipping_city' => ['required', 'string', 'max:120'],
            'shipping_province' => ['required', 'string', 'max:120'],
            'shipping_postal_code' => ['required', 'string', 'max:20'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = $this->checkout->place($store, $request->session()->getId(), $data);

        return redirect()->route('storefront.orders.show', [
            'storeSubdomain' => $request->route('storeSubdomain'),
            'orderNumber' => $order->order_number,
        ])
            ->with('success', 'Pesanan berhasil dibuat.');
    }

    public function order(string $storeSubdomain, string $orderNumber, StoreContext $context): Response
    {
        $store = $context->store();
        abort_if($store === null, 404);

        $order = Order::query()
            ->where('store_id', $store->id)
            ->where('order_number', $orderNumber)
            ->with('items')
            ->firstOrFail();

        return Inertia::render('storefront/order-confirmation', [
            'store' => ['name' => $store->name, 'currency' => $store->currency],
            'order' => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'total' => $order->total,
                'currency' => $order->currency,
                'items' => $order->items->map(fn ($item) => [
                    'product_name' => $item->product_name,
                    'variant_name' => $item->variant_name,
                    'quantity' => $item->quantity,
                    'line_total' => $item->line_total,
                ]),
            ],
        ]);
    }
}
