<?php

namespace Tests\Feature\Storefront;

use App\Enums\TeamRole;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Store;
use App\Models\Team;
use App\Models\User;
use App\Services\Storefront\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_order_snapshots_and_reserves_inventory(): void
    {
        [$store, $cart, $variant] = $this->cartWithStock(5, 1);

        $customer = $this->createCustomer($store);
        $order = app(CheckoutService::class)->place($store, 'checkout-session', $this->customerData(), $this->shippingRate(), 12345, 'Jakarta Selatan, DKI Jakarta', 2000, $customer);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('250000.00', $order->subtotal);
        $this->assertSame('265000.00', $order->total);
        $this->assertSame('15000.00', $order->shipping_amount);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'store_id' => $store->id,
            'product_name' => 'Demo Shirt',
            'variant_name' => 'Blue / M',
            'quantity' => 2,
            'track_inventory' => true,
            'unit_price' => '125000.00',
            'line_total' => '250000.00',
        ]);
        $this->assertDatabaseHas('inventories', [
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'reserved_quantity' => 3,
        ]);
        $this->assertDatabaseHas('inventory_reservations', [
            'order_id' => $order->id,
            'store_id' => $store->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'status' => 'reserved',
        ]);
        $this->assertDatabaseHas('carts', ['id' => $cart->id, 'status' => 'converted']);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => 'pending',
        ]);
    }

    public function test_checkout_rejects_insufficient_stock_without_creating_order(): void
    {
        [$store, $cart, $variant] = $this->cartWithStock(1, 0);

        try {
            app(CheckoutService::class)->place($store, 'checkout-session', $this->customerData(), $this->shippingRate(), 12345, 'Jakarta Selatan, DKI Jakarta', 2000, $this->createCustomer($store));
            $this->fail('Checkout should reject quantities above available stock.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cart', $exception->errors());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('carts', ['id' => $cart->id, 'status' => 'active']);
        $this->assertDatabaseHas('inventories', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'reserved_quantity' => 0,
        ]);
    }

    public function test_checkout_rejects_cart_from_another_store(): void
    {
        [$store, $cart] = $this->cartWithStock(5, 0);
        $otherStore = $this->makeStore();

        try {
            app(CheckoutService::class)->place($otherStore, 'checkout-session', $this->customerData(), $this->shippingRate(), 12345, 'Jakarta Selatan, DKI Jakarta', 2000, $this->createCustomer($otherStore));
            $this->fail('A cart must not be checked out through another store.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cart', $exception->errors());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('carts', ['id' => $cart->id, 'store_id' => $store->id, 'status' => 'active']);
    }

    private function cartWithStock(int $stock, int $reserved): array
    {
        $store = $this->makeStore();
        $product = $store->products()->create([
            'name' => 'Demo Shirt', 'slug' => 'demo-shirt-'.$store->id,
            'status' => 'active', 'price' => 100000, 'track_inventory' => true,
        ]);
        $variant = $product->variants()->create([
            'store_id' => $store->id, 'name' => 'Blue / M', 'sku' => 'DEMO-'.$store->id,
            'price' => 125000, 'options' => ['Color' => 'Blue', 'Size' => 'M'],
        ]);
        $variant->inventory()->create([
            'store_id' => $store->id, 'quantity' => $stock, 'reserved_quantity' => $reserved,
        ]);
        $cart = Cart::query()->create([
            'store_id' => $store->id, 'session_id' => 'checkout-session', 'status' => 'active',
        ]);
        $cart->items()->create([
            'product_id' => $product->id, 'product_variant_id' => $variant->id,
            'quantity' => 2, 'unit_price' => 100000,
        ]);

        return [$store, $cart, $variant];
    }

    private function makeStore(): Store
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

        return $team->stores()->create([
            'name' => 'Checkout Store',
            'slug' => 'checkout-store-'.$team->id,
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);
    }

    private function createCustomer(Store $store): Customer
    {
        return Customer::query()->create([
            'store_id' => $store->id,
            'name' => 'Checkout Customer',
            'email' => 'checkout-'.$store->id.'-'.uniqid().'@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
    }

    private function shippingRate(): array
    {
        return ['courier' => 'jne', 'service' => 'REG', 'amount' => 15000];
    }

    private function customerData(): array
    {
        return [
            'customer_name' => 'Demo Customer',
            'customer_email' => 'customer@example.com',
            'customer_phone' => '+628123456789',
            'shipping_address' => 'Jl. Merdeka No. 10',
            'shipping_city' => 'Jakarta Selatan',
            'shipping_province' => 'DKI Jakarta',
            'shipping_postal_code' => '12345',
        ];
    }
}
