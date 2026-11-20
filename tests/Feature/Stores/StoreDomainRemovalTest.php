<?php

namespace Tests\Feature\Stores;

use App\Enums\TeamRole;
use App\Models\StoreDomain;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreDomainRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_owner_can_remove_a_domain_while_dns_verification_is_pending(): void
    {
        [$user, $team, $store, $domain] = $this->createDomain('verifying');

        $this->actingAs($user)
            ->delete(route('teams.stores.domains.destroy', ['team' => $team, 'store' => $store, 'domain' => $domain]))
            ->assertRedirect(route('teams.stores.show', ['team' => $team, 'store' => $store]));

        $this->assertDatabaseMissing('store_domains', ['id' => $domain->id]);
    }

    public function test_team_owner_can_remove_expired_or_disabled_custom_domains(): void
    {
        foreach (['expired', 'disabled'] as $status) {
            [$user, $team, $store, $domain] = $this->createDomain($status);

            $this->actingAs($user)
                ->delete(route('teams.stores.domains.destroy', ['team' => $team, 'store' => $store, 'domain' => $domain]))
                ->assertRedirect(route('teams.stores.show', ['team' => $team, 'store' => $store]));

            $this->assertDatabaseMissing('store_domains', ['id' => $domain->id]);
        }
    }

    public function test_verified_or_active_custom_domains_must_be_disabled_before_removal(): void
    {
        foreach (['verified', 'active'] as $status) {
            [$user, $team, $store, $domain] = $this->createDomain($status);

            $this->actingAs($user)
                ->from(route('teams.stores.show', ['team' => $team, 'store' => $store]))
                ->delete(route('teams.stores.domains.destroy', ['team' => $team, 'store' => $store, 'domain' => $domain]))
                ->assertSessionHasErrors('domain');

            $this->assertDatabaseHas('store_domains', ['id' => $domain->id, 'status' => $status]);
        }
    }

    public function test_team_member_without_store_update_permission_cannot_remove_domain(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $store = $team->stores()->create(['name' => 'Main Store', 'slug' => 'main-store']);
        $domain = $store->domains()->create(['domain' => 'shop.example.test', 'type' => 'custom', 'status' => 'verifying']);

        $this->actingAs($user)
            ->delete(route('teams.stores.domains.destroy', ['team' => $team, 'store' => $store, 'domain' => $domain]))
            ->assertForbidden();

        $this->assertDatabaseHas('store_domains', ['id' => $domain->id]);
    }

    public function test_platform_subdomain_cannot_be_removed_through_custom_domain_action(): void
    {
        [$user, $team, $store] = $this->createDomain('active');
        $subdomain = $store->domains()->create(['domain' => 'main-store.platform.test', 'type' => 'subdomain', 'status' => 'active', 'is_primary' => true, 'verified_at' => now()]);

        $this->actingAs($user)
            ->delete(route('teams.stores.domains.destroy', ['team' => $team, 'store' => $store, 'domain' => $subdomain]))
            ->assertNotFound();

        $this->assertDatabaseHas('store_domains', ['id' => $subdomain->id]);
    }

    /**
     * @return array{User, Team, \App\Models\Store, StoreDomain}
     */
    private function createDomain(string $status): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $store = $team->stores()->create(['name' => 'Main Store', 'slug' => 'main-store']);
        $domain = $store->domains()->create([
            'domain' => 'shop.example.test',
            'type' => 'custom',
            'status' => $status,
            'verified_at' => in_array($status, ['verified', 'active'], true) ? now() : null,
        ]);

        return [$user, $team, $store, $domain];
    }
}
