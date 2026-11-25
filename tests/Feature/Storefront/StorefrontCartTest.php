<?php

namespace Tests\Feature\Storefront;

use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_add_a_store_product_to_the_cart_and_view_subtotal(): void
    {
        [$store, $variant] = $this->activeStoreWithVariant(stock: 5, price: 125000);

        $this->withHeader('X-Storefront-Cart-Token', 'cart-test-token-0000000000000000000000000000000000000000');
        $this->get('http://'.$store->slug.'.localhost/')->assertOk();
        $response = $this->post('http://'.$store->slug.'.localhost/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('carts', ['store_id' => $store->id, 'status' => 'active']);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => 125000,
        ]);

        $this->get('http://'.$store->slug.'.localhost/cart')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/cart')
                ->where('item_count', 2)
                ->where('subtotal', '250000.00')
                ->has('items', 1),
            );
    }

    public function test_cart_rejects_variants_owned_by_another_store(): void
    {
        [$store] = $this->activeStoreWithVariant();
        [, $foreignVariant] = $this->activeStoreWithVariant('other-store');

        $this->post('http://'.$store->slug.'.localhost/cart/items', [
            'product_variant_id' => $foreignVariant->id,
            'quantity' => 1,
        ])->assertSessionHasErrors('product_variant_id');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_cart_rejects_quantities_above_available_stock(): void
    {
        [$store, $variant] = $this->activeStoreWithVariant(stock: 2);

        $this->post('http://'.$store->slug.'.localhost/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ])->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_cart_item_quantity_can_be_updated_and_removed(): void
    {
        [$store, $variant] = $this->activeStoreWithVariant(stock: 8, price: 50000);
        $this->withHeader('X-Storefront-Cart-Token', 'cart-test-token-1111111111111111111111111111111111111111');
        $response = $this->post('http://'.$store->slug.'.localhost/cart/items', ['product_variant_id' => $variant->id]);
        $response->assertRedirect();
        $itemId = (int) DB::table('cart_items')->value('id');

        $this->patch('http://'.$store->slug.'.localhost/cart/items/'.$itemId, ['quantity' => 4])->assertRedirect();
        $this->assertDatabaseHas('cart_items', ['id' => $itemId, 'quantity' => 4]);

        $this->delete('http://'.$store->slug.'.localhost/cart/items/'.$itemId)->assertRedirect();
        $this->assertDatabaseMissing('cart_items', ['id' => $itemId]);
    }


    public function test_guest_is_redirected_to_login_before_checkout(): void
    {
        [$store, $variant] = $this->activeStoreWithVariant();
        $this->withHeader('X-Storefront-Cart-Token', 'checkout-guest-token-0000000000000000000000000000000000000000');
        $this->get('http://'.$store->slug.'.localhost/')->assertOk();
        $this->post('http://'.$store->slug.'.localhost/cart/items', ['product_variant_id' => $variant->id])->assertRedirect();

        $response = $this->get('http://'.$store->slug.'.localhost/checkout')->assertRedirect();
        $this->assertSame('/login', parse_url((string) $response->headers->get('Location'), PHP_URL_PATH));
    }

    public function test_authenticated_checkout_displays_saved_customer_profile(): void
    {
        [$store, $variant] = $this->activeStoreWithVariant();
        $this->withHeader('X-Storefront-Cart-Token', 'checkout-user-token-0000000000000000000000000000000000000000');
        $this->get('http://'.$store->slug.'.localhost/')->assertOk();
        $this->post('http://'.$store->slug.'.localhost/cart/items', ['product_variant_id' => $variant->id])->assertRedirect();

        $user = User::factory()->create([
            'name' => 'Saved Customer',
            'email' => 'saved@example.com',
            'phone' => '+628123456789',
            'shipping_address' => 'Jl. Merdeka No. 10',
            'shipping_city' => 'Jakarta Selatan',
            'shipping_province' => 'DKI Jakarta',
            'shipping_postal_code' => '12345',
        ]);

        $this->actingAs($user)->get('http://'.$store->slug.'.localhost/checkout')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/checkout')
                ->where('customer.name', 'Saved Customer')
                ->where('customer.email', 'saved@example.com')
                ->where('customer.phone', '+628123456789')
                ->where('customer.shipping_address', 'Jl. Merdeka No. 10')
                ->where('customer.shipping_postal_code', '12345'),
            );
    }

    /** @return array{Store, ProductVariant} */
    private function activeStoreWithVariant(string $slug = 'cart-store', int $stock = 5, int $price = 100000): array
    {
        $team = Team::factory()->create();
        $store = $team->stores()->create([
            'name' => 'Cart Store',
            'slug' => $slug.'-'.$team->id,
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);
        $store->domains()->create([
            'domain' => $store->slug.'.localhost',
            'type' => 'subdomain',
            'status' => 'active',
            'is_primary' => true,
            'verified_at' => now(),
        ]);
        $product = $store->products()->create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'status' => 'active',
            'price' => $price,
            'track_inventory' => true,
        ]);
        $variant = $product->variants()->create([
            'store_id' => $store->id,
            'name' => 'Default',
            'sku' => 'SKU-'.$store->id,
            'is_active' => true,
        ]);
        $variant->inventory()->create(['store_id' => $store->id, 'quantity' => $stock]);

        return [$store, $variant];
    }
}
