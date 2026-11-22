<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Services\Payments\PaymentService;
use App\Services\Shipping\ShippingQuoteService;
use App\Services\Storefront\CartService;
use App\Services\Storefront\CartSessionService;
use App\Services\Storefront\CheckoutService;
use App\Support\Stores\StoreContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckoutController extends Controller
{
    private readonly CartService $carts;

    private readonly CheckoutService $checkout;

    private readonly ShippingQuoteService $shipping;

    private readonly PaymentService $payments;

    private readonly CartSessionService $cartSessions;

    public function __construct(
        CartService $carts,
        CheckoutService $checkout,
        ShippingQuoteService $shipping,
        PaymentService $payments,
        CartSessionService $cartSessions
    ) {
        $this->carts = $carts;
        $this->checkout = $checkout;
        $this->shipping = $shipping;
        $this->payments = $payments;
        $this->cartSessions = $cartSessions;
    }

    public function show(Request $request, StoreContext $context): SymfonyResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);

        $cart = $this->carts->findCurrent($store, $this->cartSessions->id($request));
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

        $response = Inertia::render('storefront/checkout', [
            'store' => ['name' => $store->name, 'currency' => $store->currency],
            'items' => $items,
            'subtotal' => $subtotal,
            'weight_grams' => $this->shipping->weight($cart),
            'customer' => [
                'name' => auth()->user()?->name ?? '',
                'email' => auth()->user()?->email ?? '',
                'phone' => '',
            ],
        ])->toResponse($request);

        return $this->cartSessions->attach($response, $request);
    }

    public function store(Request $request, StoreContext $context): SymfonyResponse
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

        $cartSessionId = $this->cartSessions->id($request);
        $cart = Cart::query()
            ->where('store_id', $store->id)
            ->where('session_id', $cartSessionId)
            ->where('status', 'active')
            ->with('items.product')
            ->first();

        if ($cart === null) {
            $orderNumber = $request->session()->get($this->checkoutOrderSessionKey($store->id, $cartSessionId));
            $existingOrder = is_string($orderNumber)
                ? Order::query()->where('store_id', $store->id)->where('order_number', $orderNumber)->first()
                : null;

            if ($existingOrder !== null) {
                try {
                    $payment = $this->payments->start($existingOrder);

                    return $this->cartSessions->attach(Inertia::location($payment->redirect_url), $request);
                } catch (\Throwable $exception) {
                    report($exception);

                    return $this->cartSessions->attach(redirect()->route('storefront.orders.show', [
                        'storeSubdomain' => $request->route('storeSubdomain'),
                        'orderNumber' => $existingOrder->order_number,
                    ])->with('error', 'Pesanan sudah dibuat. Silakan lanjutkan pembayaran dari halaman pesanan.'), $request);
                }
            }

            abort(404);
        }

        $destinationId = (int) $data['shipping_destination_id'];
        $rate = $this->shipping->selectedRate($store, $cart, $destinationId, $data['shipping_courier'], $data['shipping_service']);
        $weight = $this->shipping->weight($cart);
        $order = $this->checkout->place(
            $store,
            $this->cartSessions->id($request),
            $data,
            $rate,
            $destinationId,
            $data['shipping_destination_label'],
            $weight,
        );

        $request->session()->put(
            $this->checkoutOrderSessionKey($store->id, $cartSessionId),
            $order->order_number,
        );

        try {
            $payment = $this->payments->start($order);

            return $this->cartSessions->attach(Inertia::location($payment->redirect_url), $request);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->cartSessions->attach(redirect()->route('storefront.orders.show', [
                'storeSubdomain' => $request->route('storeSubdomain'),
                'orderNumber' => $order->order_number,
            ])->with('error', 'Pesanan berhasil dibuat, tetapi sesi pembayaran belum tersedia. Silakan coba kembali.'), $request);
        }
    }

    private function checkoutOrderSessionKey(int $storeId, string $cartSessionId): string
    {
        return 'storefront.checkout.order.'.$storeId.'.'.hash('sha256', $cartSessionId);
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
