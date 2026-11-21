<?php

namespace App\Services\Storefront;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function place(Store $store, string $sessionId, array $data): Order
    {
        return DB::transaction(function () use ($store, $sessionId, $data): Order {
            $cart = Cart::query()
                ->where('store_id', $store->id)
                ->where('session_id', $sessionId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($cart === null) {
                throw ValidationException::withMessages(['cart' => 'Keranjang belanja kosong.']);
            }

            $items = $cart->items()->with(['product', 'variant'])->lockForUpdate()->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Keranjang belanja kosong.']);
            }

            $subtotal = 0;
            $validatedItems = [];

            foreach ($items as $item) {
                $product = Product::query()
                    ->where('store_id', $store->id)
                    ->whereKey($item->product_id)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                $variant = ProductVariant::query()
                    ->where('store_id', $store->id)
                    ->whereKey($item->product_variant_id)
                    ->where('product_id', $item->product_id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if ($product === null || $variant === null) {
                    throw ValidationException::withMessages(['cart' => 'Salah satu produk di keranjang sudah tidak tersedia.']);
                }

                $unitPrice = (float) ($variant->price ?? $product->price);
                $lineTotal = round($unitPrice * $item->quantity, 2);

                if ($product->track_inventory) {
                    $inventory = $variant->inventory()->lockForUpdate()->first();
                    $available = max(0, ($inventory?->quantity ?? 0) - ($inventory?->reserved_quantity ?? 0));

                    if ($item->quantity > $available) {
                        throw ValidationException::withMessages([
                            'cart' => 'Stok '.$product->name.' tidak mencukupi. Tersedia: '.$available.'.',
                        ]);
                    }

                    if ($inventory !== null) {
                        $inventory->increment('reserved_quantity', $item->quantity);
                    }
                }

                $subtotal += $lineTotal;
                $validatedItems[] = [
                    'store_id' => $store->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku,
                    'options' => $variant->options ?? [],
                    'quantity' => $item->quantity,
                    'unit_price' => number_format($unitPrice, 2, '.', ''),
                    'line_total' => number_format($lineTotal, 2, '.', ''),
                ];
            }

            $order = Order::query()->create([
                'store_id' => $store->id,
                'user_id' => Auth::id(),
                'order_number' => 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                'status' => 'pending',
                'payment_status' => 'pending',
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'],
                'shipping_address' => $data['shipping_address'],
                'shipping_city' => $data['shipping_city'],
                'shipping_province' => $data['shipping_province'],
                'shipping_postal_code' => $data['shipping_postal_code'],
                'customer_note' => $data['customer_note'] ?? null,
                'currency' => $store->currency,
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'shipping_amount' => '0.00',
                'discount_amount' => '0.00',
                'total' => number_format($subtotal, 2, '.', ''),
                'placed_at' => now(),
            ]);

            $order->items()->createMany($validatedItems);
            $order->statusHistories()->create([
                'store_id' => $store->id,
                'from_status' => null,
                'to_status' => 'pending',
                'note' => 'Order created from storefront checkout.',
            ]);

            $cart->update(['status' => 'converted', 'user_id' => Auth::id()]);

            return $order;
        });
    }
}
