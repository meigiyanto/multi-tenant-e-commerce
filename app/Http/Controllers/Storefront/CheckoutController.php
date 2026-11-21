<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Services\Shipping\ShippingQuoteService;
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
        private readonly ShippingQuoteService $shipping,
    ) {}

    public function show(Request $request, StoreContext $context): Response
    {
        $store = $context->store();
        abort_if($store === null, 404);

        $cart = $this->carts->findCurrent($store, $request->session()->getId());
        abort_if($cart === null || $cart->items()->doesntExist(), 404);

        $items = $this->carts->items($cart)->get()->map(function ($item): array {
            $unitPrice = $item->variant->price ?? $item->product->price;

            return [
                'id' => $item->id,
                'name' => $item->product->name,
                'variant_name' => $item->variant->name,
                'quantity' => $item->quantity,
                'unit_price' => $unitPrice,
                'line_total' => number_format((float) $unitPrice * $item->quantity, 2, '.', ''),
                'image_url' => ($image = $item->product->images->firstWhere('is_primary', true) ?? $item->product->images->first())
                    ? Storage::disk('public')->url($image->path)
                    : null,
            ];
        });
        $subtotal = number_format((float) $items->sum(fn (array $item): float => (float) $item['line_total']), 2, '.', '');
        $cart->load('items.product');

        return Inertia::render('storefront/checkout', [
            'store' => ['name' => $store->name, 'currency' => $store->currency],
            'items' => $items,
            'subtotal' => $subtotal,
            'weight_grams' => $this->shipping->weight($cart),
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
            'shipping_destination_id' => ['required', 'integer', 'min:1'],
            'shipping_destination_label' => ['required', 'string', 'max:180'],
            'shipping_courier' => ['required', 'string', 'max:40'],
            'shipping_service' => ['required', 'string', 'max:80'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $cart = Cart::query()
            ->where('store_id', $store->id)
            ->where('session_id', $request->session()->getId())
            ->where('status', 'active')
            ->with('items.product')
            ->firstOrFail();

        $destinationId = (int) $data['shipping_destination_id'];
        $rate = $this->shipping->selectedRate($store, $cart, $destinationId, $data['shipping_courier'], $data['shipping_service']);
        $weight = $this->shipping->weight($cart);
        $order = $this->checkout->place(
            $store,
            $request->session()->getId(),
            $data,
            $rate,
            $destinationId,
            $data['shipping_destination_label'],
            $weight,
        );

        return redirect()->route('storefront.orders.show', [
            'storeSubdomain' => $request->route('storeSubdomain'),
            'orderNumber' => $order->order_number,
        ])->with('success', 'Pesanan berhasil dibuat. Silakan lanjutkan pembayaran.');
    }

    public function order(string $storeSubdomain, string $orderNumber, StoreContext $context): Response
    {
        $store = $context->store();
        abort_if($store === null, 404);

        $order = Order::query()
            ->where('store_id', $store->id)
            ->where('order_number', $orderNumber)
            ->with(['items', 'paymentTransactions' => fn ($query) => $query->latest()])
            ->firstOrFail();

        return Inertia::render('storefront/order-confirmation', [
            'store' => ['name' => $store->name, 'currency' => $store->currency],
            'order' => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'subtotal' => $order->subtotal,
                'shipping_amount' => $order->shipping_amount,
                'shipping_destination_label' => $order->shipping_destination_label,
                'shipping_courier' => $order->shipping_courier,
                'shipping_service' => $order->shipping_service,
                'total' => $order->total,
                'currency' => $order->currency,
                'payment_url' => $order->paymentTransactions->first()?->redirect_url,
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
