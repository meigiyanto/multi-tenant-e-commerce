<?php

namespace App\Services\Storefront;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function current(Store $store, string $sessionId): Cart
    {
        return Cart::query()->firstOrCreate(
            ['store_id' => $store->id, 'session_id' => $sessionId, 'status' => 'active'],
        );
    }

    public function findCurrent(Store $store, string $sessionId): ?Cart
    {
        return Cart::query()
            ->where('store_id', $store->id)
            ->where('session_id', $sessionId)
            ->where('status', 'active')
            ->first();
    }

    public function countForSession(Store $store, string $sessionId): int
    {
        $cart = $this->findCurrent($store, $sessionId);

        return $cart === null ? 0 : $this->count($cart);
    }

    public function add(Store $store, string $sessionId, int $variantId, int $quantity = 1): Cart
    {
        return DB::transaction(function () use ($store, $sessionId, $variantId, $quantity): Cart {
            $variant = ProductVariant::query()
                ->where('store_id', $store->id)
                ->whereKey($variantId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($variant === null) {
                throw ValidationException::withMessages(['product_variant_id' => 'This product variant is not available in this store.']);
            }

            $product = Product::query()
                ->where('store_id', $store->id)
                ->whereKey($variant->product_id)
                ->where('status', 'active')
                ->first();

            if ($product === null) {
                throw ValidationException::withMessages(['product_variant_id' => 'This product is unavailable.']);
            }

            $cart = $this->current($store, $sessionId);
            $cart = Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $item = $cart->items()->where('product_variant_id', $variant->id)->lockForUpdate()->first();
            $newQuantity = ($item->quantity ?? 0) + $quantity;

            if ($newQuantity > 99) {
                throw ValidationException::withMessages(['quantity' => 'The maximum quantity for a single variant is 99.']);
            }

            $this->assertStock($product, $variant, $newQuantity);

            $price = $variant->price ?? $product->price;
            $cart->items()->updateOrCreate(
                ['product_variant_id' => $variant->id],
                ['product_id' => $product->id, 'quantity' => $newQuantity, 'unit_price' => $price],
            );

            return $cart->load('items');
        });
    }

    public function update(Store $store, string $sessionId, int $itemId, int $quantity): Cart
    {
        return DB::transaction(function () use ($store, $sessionId, $itemId, $quantity): Cart {
            $cart = $this->current($store, $sessionId);
            $item = $cart->items()->whereKey($itemId)->lockForUpdate()->firstOrFail();
            $variant = ProductVariant::query()->where('store_id', $store->id)->whereKey($item->product_variant_id)->where('is_active', true)->firstOrFail();
            $product = Product::query()->where('store_id', $store->id)->whereKey($item->product_id)->where('status', 'active')->firstOrFail();

            $this->assertStock($product, $variant, $quantity);
            $item->update(['quantity' => $quantity, 'unit_price' => $variant->price ?? $product->price]);

            return $cart->load('items');
        });
    }

    public function remove(Store $store, string $sessionId, int $itemId): void
    {
        $cart = $this->current($store, $sessionId);
        $cart->items()->whereKey($itemId)->delete();
    }

    /** @return HasMany<CartItem, Cart> */
    public function items(Cart $cart): HasMany
    {
        return $cart->items()->with(['product:id,name,slug,price', 'product.images:id,product_id,path,is_primary,sort_order', 'variant:id,product_id,name,options,price', 'variant.inventory']);
    }

    public function count(Cart $cart): int
    {
        return (int) $cart->items()->sum('quantity');
    }

    public function subtotal(Cart $cart): string
    {
        $subtotal = $cart->items()->selectRaw('COALESCE(SUM(quantity * unit_price), 0) as subtotal')->value('subtotal');

        return number_format((float) $subtotal, 2, '.', '');
    }

    private function assertStock(Product $product, ProductVariant $variant, int $quantity): void
    {
        if (! $product->track_inventory) {
            return;
        }

        $inventory = $variant->inventory()->lockForUpdate()->first();
        $available = max(0, ($inventory->quantity ?? 0) - ($inventory->reserved_quantity ?? 0));

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'quantity' => 'Insufficient stock. Available stock: '.$available.'.',
            ]);
        }
    }
}
