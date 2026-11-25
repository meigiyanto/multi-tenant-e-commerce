<?php

namespace Tests\Feature\Storefront;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Storefront\VerifyCustomerEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerRegistrationIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_registration_creates_a_customer_without_creating_a_platform_user_or_team_membership(): void
    {
        Notification::fake();

        $team = Team::factory()->create();
        $store = $team->stores()->create([
            'name' => 'Shop',
            'slug' => 'shop',
            'status' => 'active',
        ]);
        $store->domains()->create([
            'domain' => 'shop.localhost',
            'type' => 'custom',
            'status' => 'active',
            'is_primary' => true,
            'verified_at' => now(),
        ]);

        $usersBefore = User::query()->count();
        $teamsBefore = Team::query()->count();
        $membershipsBefore = $team->memberships()->count();

        $response = $this->post('http://shop.localhost/account/register', [
            'name' => 'Dewi Anggraini',
            'email' => 'DEWI@example.com',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ]);

        $customer = Customer::query()->where('store_id', $store->id)
            ->where('email', 'dewi@example.com')->firstOrFail();

        $response->assertRedirect();
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame($teamsBefore, Team::query()->count());
        $this->assertSame($membershipsBefore, $team->memberships()->count());
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertGuest('web');
        Notification::assertSentTo($customer, VerifyCustomerEmail::class);
    }

    public function test_customer_login_is_scoped_to_the_current_store_even_when_email_is_shared(): void
    {
        $firstTeam = Team::factory()->create();
        $firstStore = $firstTeam->stores()->create([
            'name' => 'First Shop',
            'slug' => 'first-shop',
            'status' => 'active',
        ]);
        $firstStore->domains()->create([
            'domain' => 'first-shop.localhost',
            'type' => 'custom',
            'status' => 'active',
            'is_primary' => true,
            'verified_at' => now(),
        ]);

        $secondTeam = Team::factory()->create();
        $secondStore = $secondTeam->stores()->create([
            'name' => 'Second Shop',
            'slug' => 'second-shop',
            'status' => 'active',
        ]);
        $secondStore->domains()->create([
            'domain' => 'second-shop.localhost',
            'type' => 'custom',
            'status' => 'active',
            'is_primary' => true,
            'verified_at' => now(),
        ]);

        $firstCustomer = Customer::query()->create([
            'store_id' => $firstStore->id,
            'name' => 'First Customer',
            'email' => 'shared@example.com',
            'password' => 'FirstPassword123!',
        ]);
        Customer::query()->create([
            'store_id' => $secondStore->id,
            'name' => 'Second Customer',
            'email' => 'shared@example.com',
            'password' => 'SecondPassword123!',
        ]);

        $this->post('http://first-shop.localhost/account/login', [
            'email' => 'shared@example.com',
            'password' => 'SecondPassword123!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('customer');

        $this->post('http://first-shop.localhost/account/login', [
            'email' => 'shared@example.com',
            'password' => 'FirstPassword123!',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($firstCustomer, 'customer');
        $this->assertGuest('web');
    }

    public function test_login_does_not_reuse_a_browser_cart_owned_by_another_customer(): void
    {
        $team = Team::factory()->create();
        $store = $team->stores()->create([
            'name' => 'Shop',
            'slug' => 'shop',
            'status' => 'active',
        ]);
        $store->domains()->create([
            'domain' => 'shop.localhost',
            'type' => 'custom',
            'status' => 'active',
            'is_primary' => true,
            'verified_at' => now(),
        ]);
        $owner = Customer::query()->create([
            'store_id' => $store->id,
            'name' => 'Cart Owner',
            'email' => 'owner@example.com',
            'password' => 'OwnerPassword123!',
        ]);
        Customer::query()->create([
            'store_id' => $store->id,
            'name' => 'New Customer',
            'email' => 'new@example.com',
            'password' => 'NewPassword123!',
        ]);
        $cart = Cart::query()->create([
            'store_id' => $store->id,
            'customer_id' => $owner->id,
            'session_id' => 'shared-browser-cart-token-12345678901234567890',
            'status' => 'active',
        ]);

        $response = $this->withHeader('X-Storefront-Cart-Token', $cart->session_id)
            ->post('http://shop.localhost/account/login', [
                'email' => 'new@example.com',
                'password' => 'NewPassword123!',
            ])->assertRedirect();

        $this->assertDatabaseHas('carts', [
            'id' => $cart->id,
            'customer_id' => $owner->id,
        ]);
        $this->assertNotEmpty($response->headers->getCookies());
    }

    public function test_authenticated_customer_remains_visible_on_storefront_when_domain_differs_from_store_slug(): void
    {
        $team = Team::factory()->create();
        $store = $team->stores()->create([
            'name' => 'Nuvora',
            'slug' => 'nuvora',
            'status' => 'active',
        ]);
        $store->domains()->create([
            'domain' => 'nuvora-1.localhost',
            'type' => 'custom',
            'status' => 'active',
            'is_primary' => true,
            'verified_at' => now(),
        ]);
        $customer = Customer::query()->create([
            'store_id' => $store->id,
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'StrongPassword123!',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($customer, 'customer')
            ->get('http://nuvora-1.localhost/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.id', $customer->id)
                ->where('auth.user.name', 'Budi Santoso'));
    }

    public function test_platform_user_is_not_converted_into_a_customer_during_storefront_login(): void
    {
        $team = Team::factory()->create();
        $store = $team->stores()->create([
            'name' => 'Shop',
            'slug' => 'shop',
            'status' => 'active',
        ]);
        $store->domains()->create([
            'domain' => 'shop.localhost',
            'type' => 'custom',
            'status' => 'active',
            'is_primary' => true,
            'verified_at' => now(),
        ]);
        $platformUser = User::factory()->create([
            'email' => 'platform@example.com',
            'password' => 'StrongPassword123!',
        ]);

        $response = $this->post('http://shop.localhost/account/login', [
            'email' => $platformUser->email,
            'password' => 'StrongPassword123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('customers', [
            'store_id' => $store->id,
            'email' => $platformUser->email,
        ]);
        $this->assertDatabaseHas('users', ['id' => $platformUser->id]);
        $this->assertGuest('customer');
        $this->assertGuest('web');
    }
}
