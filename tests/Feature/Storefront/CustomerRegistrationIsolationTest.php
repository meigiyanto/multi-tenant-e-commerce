<?php

namespace Tests\Feature\Storefront;

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
