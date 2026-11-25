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

    public function __construct(CartService $carts, CheckoutService $checkout, ShippingQuoteService $shipping, PaymentService $payments, CartSessionService $cartSessions)
    {
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

        $user = $request->user('customer');
        if (! $this->hasCompleteShippingProfile($user)) {
            $request->session()->put('url.intended', route('storefront.checkout.show', ['storeSubdomain' => $store->slug]));

            return redirect()->route('storefront.account.profile', ['storeSubdomain' => $store->slug])->with('error', 'Complete your contact information and default shipping address before checkout.');
        }

        $items = $this->carts->items($cart)->get()->map(function ($item): array {
            $product = $item->product;
            $variant = $item->variant;
            $unitPrice = $variant?->price ?? $product?->price ?? $item->unit_price;
            $images = $product?->images ?? collect();
            $image = $images->firstWhere('is_primary', true) ?? $images->first();

            return [
                'id' => $item->id,
                'name' => $product?->name ?? 'Product unavailable',
                'variant_name' => $variant?->name ?? 'Variant unavailable',
                'quantity' => $item->quantity,
                'unit_price' => $unitPrice,
                'line_total' => number_format((float) $unitPrice * $item->quantity, 2, '.', ''),
                'image_url' => $image ? Storage::disk('public')->url($image->path) : null,
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
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'shipping_address' => $user->shipping_address ?? '',
                'shipping_city' => $user->shipping_city ?? '',
                'shipping_province' => $user->shipping_province ?? '',
                'shipping_postal_code' => $user->shipping_postal_code ?? '',
            ],
        ])->toResponse($request);

        return $this->cartSessions->attach($response, $request);
    }

    public function store(Request $request, StoreContext $context): SymfonyResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);
        $user = $request->user('customer');

        if (! $this->hasCompleteShippingProfile($user)) {
            return redirect()->route('storefront.account.profile', ['storeSubdomain' => $store->slug])->with('error', 'Complete your phone number and default shipping address before placing an order.');
        }

        $data = $request->validate([
            'shipping_city' => ['required', 'string', 'max:120'],
            'shipping_province' => ['required', 'string', 'max:120'],
            'shipping_destination_id' => ['required', 'integer', 'min:1'],
            'shipping_destination_label' => ['required', 'string', 'max:180'],
            'shipping_courier' => ['required', 'string', 'max:40'],
            'shipping_service' => ['required', 'string', 'max:80'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $data = [
            ...$data,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'shipping_address' => $user->shipping_address,
            'shipping_postal_code' => $user->shipping_postal_code,
        ];

        $cart = Cart::query()
            ->where('store_id', $store->id)
            ->where('session_id', $this->cartSessions->id($request))
            ->where('status', 'active')
            ->with('items.product')
            ->firstOrFail();

        $destinationId = (int) $data['shipping_destination_id'];
        $rate = $this->shipping->selectedRate($store, $cart, $destinationId, $data['shipping_courier'], $data['shipping_service']);
        $weight = $this->shipping->weight($cart);
        $order = $this->checkout->place($store, $this->cartSessions->id($request), $data, $rate, $destinationId, $data['shipping_destination_label'], $weight, $user);

        try {
            $payment = $this->payments->start($order);
            $response = Inertia::location($payment->redirect_url);

            return $this->cartSessions->attach($response, $request);
        } catch (\Throwable $exception) {
            report($exception);
            $appKey = (string) config('app.key');
            $expires = now()->addDays(7)->timestamp;
            $finishKey = hash_hmac('sha256', $order->order_number.'|'.$expires, $appKey);
            $finishUrl = rtrim((string) config('app.url'), '/').'/orders/'.rawurlencode($order->order_number)
                .'?expires='.$expires.'&key='.$finishKey;

            return $this->cartSessions->attach(redirect()->to($finishUrl)->with('error', 'Your order was created, but the payment session is unavailable. Please try again.'), $request);
        }
    }

    private function hasCompleteShippingProfile(\App\Models\Customer $user): bool
    {
        return filled($user->phone)
            && filled($user->shipping_address)
            && filled($user->shipping_city)
            && filled($user->shipping_province)
            && filled($user->shipping_postal_code);
    }

    public function order(Request $request, string $storeSubdomain, string $orderNumber, StoreContext $context): Response
    {
        return $this->renderOrder($orderNumber, $context, (int) $request->user('customer')->id);
    }

    public function finish(string $orderNumber, StoreContext $context): Response
    {
        return $this->renderOrder($orderNumber, $context);
    }

    private function renderOrder(string $orderNumber, StoreContext $context, ?int $customerId = null): Response
    {
        $store = $context->store();
        abort_if($store === null, 404);
        $order = Order::query()->where('store_id', $store->id)->where('order_number', $orderNumber)
            ->when($customerId !== null, fn ($query) => $query->where('customer_id', $customerId))
            ->with(['items', 'paymentTransactions' => fn ($query) => $query->latest()])->firstOrFail();

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
                'tracking_url' => route('storefront.orders.track', ['orderNumber' => $order->order_number, 'token' => hash_hmac('sha256', 'order-tracking|'.$order->order_number, (string) config('app.key'))]),
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
