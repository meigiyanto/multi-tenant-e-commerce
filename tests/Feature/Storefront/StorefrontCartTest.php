<?php

namespace Tests\Feature\Storefront;

use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Team;
use App\Notifications\Storefront\VerifyCustomerEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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


    public function test_storefront_displays_the_authenticated_customer_identity(): void
    {
        [$store] = $this->activeStoreWithVariant();
        $customer = $this->createCustomer($store, ['name' => 'Storefront Customer', 'email' => 'customer@example.com']);

        $this->actingAs($customer, 'customer')
            ->get('http://'.$store->slug.'.localhost/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/index')
                ->where('auth.user.id', $customer->id)
                ->where('auth.user.name', 'Storefront Customer')
                ->where('auth.user.email', 'customer@example.com'),
            );
    }

    public function test_guest_storefront_exposes_no_authenticated_customer(): void
    {
        [$store] = $this->activeStoreWithVariant();

        $this->get('http://'.$store->slug.'.localhost/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/index')
                ->where('auth.user', null),
            );
    }

    public function test_authenticated_customer_can_open_storefront_account_pages(): void
    {
        [$store] = $this->activeStoreWithVariant();
        $customer = $this->createCustomer($store, ['name' => 'Account Customer']);

        $this->actingAs($customer, 'customer')
            ->get('http://'.$store->slug.'.localhost/account')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/account/index')
                ->where('summary.orders_count', 0)
                ->where('summary.profile_complete', false)
                ->has('latest_orders', 0),
            );

        $this->get('http://'.$store->slug.'.localhost/account/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/account/profile')
                ->where('profile.name', 'Account Customer'),
            );

        $this->get('http://'.$store->slug.'.localhost/account/orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/account/orders')
                ->has('orders', 0),
            );
    }

    public function test_customer_order_history_is_limited_to_the_current_store_and_customer(): void
    {
        [$store] = $this->activeStoreWithVariant();
        [$otherStore] = $this->activeStoreWithVariant('other-store');
        $customer = $this->createCustomer($store, ['email' => 'same@example.com']);
        $otherStoreCustomer = $this->createCustomer($otherStore, ['email' => 'same@example.com']);
        $otherCustomer = $this->createCustomer($store, ['email' => 'other@example.com']);

        $this->createLegacyOrder($store, 'ORD-OWN-LOCAL', 'same@example.com', null, $customer->id);
        $this->createLegacyOrder($otherStore, 'ORD-OTHER-STORE', 'same@example.com', null, $otherStoreCustomer->id);
        $this->createLegacyOrder($store, 'ORD-OTHER-CUSTOMER', 'other@example.com', null, $otherCustomer->id);

        $this->actingAs($customer, 'customer')
            ->get('http://'.$store->slug.'.localhost/account/orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/account/orders')
                ->has('orders', 1)
                ->where('orders.0.order_number', 'ORD-OWN-LOCAL'),
            );

        $this->get('http://'.$store->slug.'.localhost/account')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.orders_count', 1)
                ->has('latest_orders', 1),
            );
    }

    public function test_unverified_customer_is_redirected_to_verification_and_can_resend_email(): void
    {
        Notification::fake();

        [$store] = $this->activeStoreWithVariant();
        $customer = $this->createCustomer($store, ['email' => 'unverified@example.com', 'email_verified_at' => null]);

        $this->actingAs($customer, 'customer')
            ->get('http://'.$store->slug.'.localhost/account')
            ->assertRedirect('http://'.$store->slug.'.localhost/account/verify-email');

        $this->get('http://'.$store->slug.'.localhost/account/verify-email')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/account/verify-email')
                ->where('email', 'unverified@example.com'),
            );

        $this->post('http://'.$store->slug.'.localhost/account/verification-notification')
            ->assertRedirect()
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($customer, VerifyCustomerEmail::class);
    }

    public function test_guest_cannot_open_storefront_account_pages(): void
    {
        [$store] = $this->activeStoreWithVariant();

        $this->get('http://'.$store->slug.'.localhost/account')->assertRedirect('http://'.$store->slug.'.localhost/account/login');
        $this->get('http://'.$store->slug.'.localhost/account/profile')->assertRedirect('http://'.$store->slug.'.localhost/account/login');
        $this->get('http://'.$store->slug.'.localhost/account/orders')->assertRedirect('http://'.$store->slug.'.localhost/account/login');
    }

    public function test_storefront_login_and_registration_are_customer_specific_pages(): void
    {
        [$store] = $this->activeStoreWithVariant();

        $this->get('http://'.$store->slug.'.localhost/account/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('storefront/account/login'));
        $this->get('http://'.$store->slug.'.localhost/account/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('storefront/account/register'));
    }

    public function test_guest_is_redirected_to_customer_login_before_checkout(): void
    {
        [$store, $variant] = $this->activeStoreWithVariant();
        $this->withHeader('X-Storefront-Cart-Token', 'checkout-guest-token-0000000000000000000000000000000000000000');
        $this->get('http://'.$store->slug.'.localhost/')->assertOk();
        $this->post('http://'.$store->slug.'.localhost/cart/items', ['product_variant_id' => $variant->id])->assertRedirect();

        $response = $this->get('http://'.$store->slug.'.localhost/checkout')->assertRedirect();
        $this->assertSame('/account/login', parse_url((string) $response->headers->get('Location'), PHP_URL_PATH));
    }

    public function test_authenticated_customer_without_shipping_profile_is_redirected_to_profile_settings(): void
    {
        [$store, $variant] = $this->activeStoreWithVariant();
        $this->withHeader('X-Storefront-Cart-Token', 'checkout-profile-token-0000000000000000000000000000000000000000');
        $this->get('http://'.$store->slug.'.localhost/')->assertOk();
        $this->post('http://'.$store->slug.'.localhost/cart/items', ['product_variant_id' => $variant->id])->assertRedirect();

        $customer = $this->createCustomer($store);
        $response = $this->actingAs($customer, 'customer')->get('http://'.$store->slug.'.localhost/checkout');

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertSame('/account/profile', parse_url((string) $response->headers->get('Location'), PHP_URL_PATH));
    }

    public function test_authenticated_checkout_displays_saved_customer_profile(): void
    {
        [$store, $variant] = $this->activeStoreWithVariant();
        $this->withHeader('X-Storefront-Cart-Token', 'checkout-user-token-0000000000000000000000000000000000000000');
        $this->get('http://'.$store->slug.'.localhost/')->assertOk();
        $this->post('http://'.$store->slug.'.localhost/cart/items', ['product_variant_id' => $variant->id])->assertRedirect();

        $customer = $this->createCustomer($store, [
            'name' => 'Saved Customer',
            'email' => 'saved@example.com',
            'phone' => '+628123456789',
            'shipping_address' => 'Jl. Merdeka No. 10',
            'shipping_city' => 'Jakarta Selatan',
            'shipping_province' => 'DKI Jakarta',
            'shipping_postal_code' => '12345',
        ]);

        $this->actingAs($customer, 'customer')->get('http://'.$store->slug.'.localhost/checkout')
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

    public function test_customer_can_change_password_from_their_profile(): void
    {
        [$store] = $this->activeStoreWithVariant();
        $customer = $this->createCustomer($store, ['password' => 'OldPassword123!']);

        $this->actingAs($customer, 'customer')
            ->patch('http://'.$store->slug.'.localhost/account/profile', [
                'name' => $customer->name,
                'email' => $customer->email,
                'current_password' => 'OldPassword123!',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect()
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Profile updated.']);

        $this->assertTrue(Hash::check('NewPassword123!', $customer->fresh()->password));
    }

    public function test_customer_profile_keeps_existing_password_when_password_fields_are_blank(): void
    {
        [$store] = $this->activeStoreWithVariant();
        $customer = $this->createCustomer($store, ['password' => 'OldPassword123!']);
        $oldHash = $customer->password;

        $this->actingAs($customer, 'customer')
            ->patch('http://'.$store->slug.'.localhost/account/profile', [
                'name' => 'Updated Customer',
                'email' => $customer->email,
                'current_password' => '',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect();

        $this->assertSame($oldHash, $customer->fresh()->password);
        $this->assertSame('Updated Customer', $customer->fresh()->name);
    }

    public function test_changing_customer_email_resets_verification_and_sends_a_new_link(): void
    {
        Notification::fake();
        [$store] = $this->activeStoreWithVariant();
        $customer = $this->createCustomer($store, ['email' => 'old-customer@example.com']);

        $this->actingAs($customer, 'customer')
            ->patch('http://'.$store->slug.'.localhost/account/profile', [
                'name' => $customer->name,
                'email' => 'new-customer@example.com',
            ])
            ->assertRedirect('http://'.$store->slug.'.localhost/account/verify-email');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'email' => 'new-customer@example.com',
            'email_verified_at' => null,
        ]);
        Notification::assertSentTo($customer, VerifyCustomerEmail::class);
    }

    private function createCustomer(Store $store, array $attributes = []): Customer
    {
        return Customer::query()->create(array_merge([
            'store_id' => $store->id,
            'name' => 'Store Customer',
            'email' => 'customer-'.\Illuminate\Support\Str::uuid().'@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ], $attributes));
    }

    private function createLegacyOrder(Store $store, string $number, string $email, ?int $userId, ?int $customerId = null): Order
    {
        return Order::query()->create([
            'store_id' => $store->id,
            'user_id' => $userId,
            'customer_id' => $customerId,
            'order_number' => $number,
            'status' => 'pending',
            'payment_status' => 'pending',
            'customer_name' => 'Legacy Customer',
            'customer_email' => $email,
            'customer_phone' => '08123456789',
            'shipping_address' => 'Jalan Merdeka 1',
            'shipping_city' => 'Jakarta',
            'shipping_province' => 'DKI Jakarta',
            'shipping_postal_code' => '10110',
            'currency' => $store->currency,
            'subtotal' => '100000.00',
            'shipping_amount' => '10000.00',
            'discount_amount' => '0.00',
            'total' => '110000.00',
            'placed_at' => now(),
        ]);
    }

    /** @return array{Store, ProductVariant} */
    private function activeStoreWithVariant(string $slug = 'cart-store', int $stock = 5, int $price = 100000): array
    {
        $team = Team::factory()->create();
        $store = $team->stores()->create([
            'name' => $slug === 'other-store' ? 'Other Store' : 'Cart Store',
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
