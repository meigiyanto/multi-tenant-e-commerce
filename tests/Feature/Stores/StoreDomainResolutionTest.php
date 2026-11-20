<?php

namespace Tests\Feature\Stores;

use App\Http\Middleware\ResolveStoreFromDomain;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\Team;
use App\Support\Stores\StoreContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StoreDomainResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(ResolveStoreFromDomain::class)
            ->get('/__storefront-context', function (Request $request, StoreContext $context) {
                return response()->json([
                    'store_id' => $context->store()?->id,
                    'domain_id' => $context->domain()?->id,
                    'team_id' => $context->team()?->id,
                    'request_store_id' => $request->attributes->get('store')?->id,
                ]);
            });
    }

    public function test_verified_active_domain_resolves_store_and_team_context(): void
    {
        [$store, $domain] = $this->createStoreDomain();

        $response = $this->get('http://shop.example.test/__storefront-context');

        $response->assertOk()
            ->assertJsonPath('store_id', $store->id)
            ->assertJsonPath('domain_id', $domain->id)
            ->assertJsonPath('team_id', $store->team_id)
            ->assertJsonPath('request_store_id', $store->id);
    }

    public function test_unknown_domain_returns_not_found(): void
    {
        $this->get('http://unknown.example.test/__storefront-context')
            ->assertNotFound();
    }

    public function test_pending_domain_cannot_resolve_a_store(): void
    {
        $this->createStoreDomain(status: 'pending');

        $this->get('http://shop.example.test/__storefront-context')
            ->assertNotFound();
    }

    public function test_unverified_domain_cannot_resolve_a_store(): void
    {
        $this->createStoreDomain(verified: false);

        $this->get('http://shop.example.test/__storefront-context')
            ->assertNotFound();
    }

    public function test_inactive_store_cannot_be_resolved(): void
    {
        $this->createStoreDomain(storeStatus: 'suspended');

        $this->get('http://shop.example.test/__storefront-context')
            ->assertNotFound();
    }

    public function test_suspended_team_cannot_resolve_its_storefront(): void
    {
        $this->createStoreDomain(teamStatus: 'suspended');

        $this->get('http://shop.example.test/__storefront-context')
            ->assertNotFound();
    }

    public function test_cancelled_team_cannot_resolve_its_storefront(): void
    {
        $this->createStoreDomain(teamStatus: 'cancelled');

        $this->get('http://shop.example.test/__storefront-context')
            ->assertNotFound();
    }

    public function test_soft_deleted_store_cannot_be_resolved(): void
    {
        [$store] = $this->createStoreDomain();
        $store->delete();

        $this->get('http://shop.example.test/__storefront-context')
            ->assertNotFound();
    }

    /**
     * @return array{Store, StoreDomain}
     */
    private function createStoreDomain(
        string $status = 'active',
        bool $verified = true,
        string $storeStatus = 'active',
        string $teamStatus = 'active',
    ): array {
        $team = Team::factory()->create(['status' => $teamStatus]);

        $store = $team->stores()->create([
            'name' => 'Example Store',
            'slug' => 'example-store',
            'status' => $storeStatus,
        ]);

        $domain = $store->domains()->create([
            'domain' => 'SHOP.Example.Test.',
            'type' => 'custom',
            'status' => $status,
            'is_primary' => true,
            'verified_at' => $verified ? now() : null,
        ]);

        return [$store, $domain];
    }
}
