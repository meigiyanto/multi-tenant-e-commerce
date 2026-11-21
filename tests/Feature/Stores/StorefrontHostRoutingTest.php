<?php

namespace Tests\Feature\Stores;

use App\Models\Store;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontHostRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_subdomain_routes_to_its_storefront_instead_of_the_application_homepage(): void
    {
        $team = Team::factory()->create(['status' => 'active']);
        $store = $team->stores()->create([
            'name' => 'Example Store',
            'slug' => 'example-store',
            'status' => 'active',
            'currency' => 'IDR',
        ]);

        $store->domains()->create([
            'domain' => 'shop.localhost',
            'type' => 'platform',
            'status' => 'active',
            'is_primary' => true,
            'verified_at' => now(),
        ]);

        $this->get('http://shop.localhost/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/index')
                ->where('store.name', 'Example Store')
                ->where('store.slug', 'example-store')
                ->where('domain', 'shop.localhost'),
            );
    }

    public function test_unknown_platform_subdomain_does_not_fall_back_to_the_application_homepage(): void
    {
        $this->get('http://unknown.localhost/')
            ->assertNotFound();
    }
}
