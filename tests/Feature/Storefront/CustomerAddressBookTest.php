<?php

namespace Tests\Feature\Storefront;

use App\Models\Customer;
use App\Models\Store;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAddressBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_add_multiple_addresses_and_keep_only_one_default(): void
    {
        [$store, $customer] = $this->storeAndCustomer();

        $this->actingAs($customer, 'customer')->post('http://shop.localhost/account/addresses', $this->addressData([
            'label' => 'Rumah',
            'type' => 'home',
            'is_default' => true,
        ]))->assertRedirect();

        $home = $customer->addresses()->where('label', 'Rumah')->firstOrFail();

        $this->post('http://shop.localhost/account/addresses', $this->addressData([
            'label' => 'Kantor',
            'type' => 'office',
            'is_default' => true,
        ]))->assertRedirect();

        $this->assertDatabaseHas('customer_addresses', ['id' => $home->id, 'is_default' => false]);
        $office = $customer->addresses()->where('label', 'Kantor')->firstOrFail();
        $this->assertTrue($office->is_default);

        $this->put('http://shop.localhost/account/addresses/'.$home->id.'/default')->assertRedirect();
        $this->assertDatabaseHas('customer_addresses', ['id' => $home->id, 'is_default' => true]);
        $this->assertDatabaseHas('customer_addresses', ['id' => $office->id, 'is_default' => false]);
    }

    public function test_deleting_default_address_promotes_another_saved_address(): void
    {
        [$store, $customer] = $this->storeAndCustomer();
        $home = $customer->addresses()->create($this->addressData(['store_id' => $store->id, 'label' => 'Rumah', 'type' => 'home', 'is_default' => true]));
        $office = $customer->addresses()->create($this->addressData(['store_id' => $store->id, 'label' => 'Kantor', 'type' => 'office']));

        $this->actingAs($customer, 'customer')
            ->delete('http://shop.localhost/account/addresses/'.$home->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('customer_addresses', ['id' => $home->id]);
        $this->assertDatabaseHas('customer_addresses', ['id' => $office->id, 'is_default' => true]);
    }

    public function test_customer_cannot_delete_another_customers_address(): void
    {
        [$store, $customer] = $this->storeAndCustomer();
        $other = Customer::query()->create([
            'store_id' => $store->id,
            'name' => 'Other Customer',
            'email' => 'other@example.com',
            'password' => 'StrongPassword123!',
        ]);
        $address = $other->addresses()->create($this->addressData(['store_id' => $store->id]));

        $this->actingAs($customer, 'customer')
            ->delete('http://shop.localhost/account/addresses/'.$address->id)
            ->assertNotFound();
    }

    /** @return array{0: Store, 1: Customer} */
    private function storeAndCustomer(): array
    {
        $team = Team::factory()->create();
        $store = $team->stores()->create(['name' => 'Shop', 'slug' => 'shop', 'status' => 'active']);
        $store->domains()->create([
            'domain' => 'shop.localhost',
            'type' => 'custom',
            'status' => 'active',
            'is_primary' => true,
            'verified_at' => now(),
        ]);
        $customer = Customer::query()->create([
            'store_id' => $store->id,
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'password' => 'StrongPassword123!',
            'phone' => '08123456789',
        ]);
        $customer->forceFill(['email_verified_at' => now()])->save();

        return [$store, $customer];
    }

    /** @return array<string, mixed> */
    private function addressData(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Alamat utama',
            'type' => 'home',
            'recipient_name' => 'Customer',
            'phone' => '08123456789',
            'address' => 'Jl. Merdeka No. 1',
            'city' => 'Banyumas',
            'province' => 'Jawa Tengah',
            'postal_code' => '53191',
        ], $overrides);
    }
}
