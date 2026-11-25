<?php

namespace App\Services\Storefront;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    /**
     * @phpstan-param array{customer_name: string, customer_email: string, customer_phone: string, shipping_address: string, shipping_city: string, shipping_province: string, shipping_postal_code: string, customer_note?: string|null} $data
     * @phpstan-param array{courier: string, service: string, amount: int} $shippingRate
     */
    public function place(Store $store, string $sessionId, array $data, array $shippingRate, int $destinationId, string $destinationLabel, int $weightGrams, Customer $customer): Order
    {
        if ((int) $customer->store_id !== (int) $store->id) {
            throw ValidationException::withMessages(['customer' => 'This customer account does not belong to the selected store.']);
        }

        return DB::transaction(function () use ($store, $sessionId, $data, $shippingRate, $destinationId, $destinationLabel, $weightGrams, $customer): Order {
            $cart = Cart::query()
                ->where('store_id', $store->id)
                ->where('session_id', $sessionId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($cart === null) {
                throw ValidationException::withMessages(['cart' => 'The shopping cart is empty.']);
            }

            $items = $cart->items()->with(['product', 'variant'])->lockForUpdate()->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'The shopping cart is empty.']);
            }

            $subtotal = 0;
            $actualWeightGrams = 0;
            $validatedItems = [];
            $reservationQuantities = [];

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
                    throw ValidationException::withMessages(['cart' => 'A product in your cart is no longer available.']);
                }

                $actualWeightGrams += (int) ($product->weight_grams ?? 1000) * (int) $item->quantity;
                $unitPrice = (float) ($variant->price ?? $product->price);
                $lineTotal = round($unitPrice * $item->quantity, 2);

                if ($product->track_inventory) {
                    $inventory = $variant->inventory()->lockForUpdate()->first();
                    $available = max(0, ($inventory->quantity ?? 0) - ($inventory->reserved_quantity ?? 0));

                    if ($item->quantity > $available) {
                        throw ValidationException::withMessages([
                            'cart' => 'Stock for '.$product->name.' is insufficient. Available: '.$available.'.',
                        ]);
                    }

                    if ($inventory !== null) {
                        $reservationQuantities[$variant->id] = ($reservationQuantities[$variant->id] ?? 0) + (int) $item->quantity;
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
                    'track_inventory' => (bool) $product->track_inventory,
                    'unit_price' => number_format($unitPrice, 2, '.', ''),
                    'line_total' => number_format($lineTotal, 2, '.', ''),
                ];
            }

            if (max(1, $actualWeightGrams) !== $weightGrams) {
                throw ValidationException::withMessages(['shipping_service' => 'The cart weight has changed. Please recalculate the shipping rate.']);
            }

            $shippingAmount = (int) $shippingRate['amount'];
            $order = Order::query()->create([
                'store_id' => $store->id,
                'user_id' => null,
                'customer_id' => $customer->id,
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
                'shipping_destination_id' => $destinationId,
                'shipping_destination_label' => $destinationLabel,
                'shipping_provider' => 'rajaongkir',
                'shipping_courier' => $shippingRate['courier'],
                'shipping_service' => $shippingRate['service'],
                'shipping_weight_grams' => $weightGrams,
                'customer_note' => $data['customer_note'] ?? null,
                'currency' => $store->currency,
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'shipping_amount' => number_format($shippingAmount, 2, '.', ''),
                'discount_amount' => '0.00',
                'total' => number_format($subtotal + $shippingAmount, 2, '.', ''),
                'placed_at' => now(),
            ]);

            $order->items()->createMany($validatedItems);

            foreach ($reservationQuantities as $variantId => $quantity) {
                $inventory = Inventory::query()
                    ->where('store_id', $store->id)
                    ->where('product_variant_id', $variantId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $available = max(0, $inventory->quantity - $inventory->reserved_quantity);
                if ($quantity > $available) {
                    throw ValidationException::withMessages([
                        'cart' => 'Stock changed during checkout. Please review your cart.',
                    ]);
                }

                $inventory->increment('reserved_quantity', $quantity);
                InventoryReservation::query()->create([
                    'store_id' => $store->id,
                    'order_id' => $order->id,
                    'inventory_id' => $inventory->id,
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                    'status' => 'reserved',
                ]);
            }

            $order->statusHistories()->create([
                'store_id' => $store->id,
                'from_status' => null,
                'to_status' => 'pending',
                'note' => 'Order created from storefront checkout.',
            ]);

            $cart->update(['status' => 'converted', 'user_id' => null, 'customer_id' => $customer->id]);

            return $order;
        });
    }
}
