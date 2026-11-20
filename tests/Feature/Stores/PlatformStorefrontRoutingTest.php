<?php

namespace Tests\Feature\Stores;

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformStorefrontRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_platform_subdomain_resolves_an_active_storefront(): void
    {
        $team = Team::factory()->create();
        $store = $team->stores()->create([
            'name' => 'My Online Store',
            'slug' => 'my-online-store',
            'status' => 'active',
        ]);
        $domain = $store->domains()->create([
            'domain' => 'my-online-store-'.$store->id.'.localhost',
            'type' => 'subdomain',
            'is_primary' => true,
            'status' => 'active',
            'verified_at' => now(),
        ]);

        $this->get('http://'.$domain->domain.'/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/index')
                ->where('store.name', 'My Online Store')
                ->where('store.slug', 'my-online-store')
                ->where('domain', $domain->domain),
            );
    }

    public function test_unknown_platform_subdomain_does_not_resolve_a_storefront(): void
    {
        config(['storefront.root_domain' => 'platform.test']);

        $this->get('http://unknown-store.localhost/')
            ->assertNotFound();
    }
}
