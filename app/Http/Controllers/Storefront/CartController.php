<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Services\Storefront\CartService;
use App\Services\Storefront\CartSessionService;
use App\Support\Stores\StoreContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CartSessionService $cartSessions,
    ) {}

    public function show(string $storeSubdomain, Request $request, StoreContext $context): SymfonyResponse
    {
        $store = $context->store();

        abort_if($store === null, 404);

        $sessionId = $this->cartSessions->id($request);
        $cart = $this->carts->current($store, $sessionId);
        $this->syncCustomer($store->id, $sessionId, $request);
        $items = $this->carts->items($cart)->get()->map(fn ($item): array => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'variant_id' => $item->product_variant_id,
            'name' => $item->product->name,
            'slug' => $item->product->slug,
            'image_url' => ($image = $item->product->images->firstWhere('is_primary', true) ?? $item->product->images->first())
                ? Storage::disk('public')->url($image->path)
                : null,
            'variant_name' => $item->variant->name,
            'options' => $item->variant->options ?? [],
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'line_total' => number_format((float) $item->unit_price * $item->quantity, 2, '.', ''),
            'stock' => $item->variant->inventory
                ? max(0, $item->variant->inventory->quantity - $item->variant->inventory->reserved_quantity)
                : null,
        ]);

        $response = Inertia::render('storefront/cart', [
            'store' => ['name' => $store->name, 'currency' => $store->currency],
            'items' => $items,
            'item_count' => $this->carts->count($cart),
            'subtotal' => $this->carts->subtotal($cart),
        ])->toResponse($request);

        return $this->cartSessions->attach($response, $request);
    }

    public function store(string $storeSubdomain, Request $request, StoreContext $context): SymfonyResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);

        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:99'],
        ]);

        $sessionId = $this->cartSessions->id($request);
        $this->carts->add($store, $sessionId, $data['product_variant_id'], $data['quantity'] ?? 1);
        $this->syncCustomer($store->id, $sessionId, $request);

        return $this->cartSessions->attach(back()->with('success', 'Product added to cart.'), $request);
    }

    public function update(string $storeSubdomain, Request $request, int $item, StoreContext $context): SymfonyResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);
        $sessionId = $this->cartSessions->id($request);
        $this->carts->update($store, $sessionId, $item, $data['quantity']);
        $this->syncCustomer($store->id, $sessionId, $request);

        return $this->cartSessions->attach(back()->with('success', 'Product quantity updated.'), $request);
    }

    private function syncCustomer(int $storeId, string $sessionId, Request $request): void
    {
        $customer = $request->user('customer');
        if ($customer === null) {
            return;
        }

        Cart::query()->where('store_id', $storeId)
            ->where('session_id', $sessionId)->where('status', 'active')
            ->where(function ($query) use ($customer): void {
                $query->whereNull('customer_id')->orWhere('customer_id', $customer->id);
            })
            ->update(['customer_id' => $customer->id]);
    }

    public function destroy(string $storeSubdomain, Request $request, int $item, StoreContext $context): SymfonyResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);

        $sessionId = $this->cartSessions->id($request);
        $this->carts->remove($store, $sessionId, $item);
        $this->syncCustomer($store->id, $sessionId, $request);

        return $this->cartSessions->attach(back()->with('success', 'Product removed from cart.'), $request);
    }
}
