<?php

namespace Tests\Feature\Stores;

use App\Contracts\Stores\DomainOwnershipVerifier;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreDomainManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_owner_can_add_and_verify_a_custom_domain_with_dns_txt(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $store = $team->stores()->create(['name' => 'Domain Store', 'slug' => 'domain-store']);

        $this->actingAs($user)
            ->post(route('teams.stores.domains.store', ['team' => $team, 'store' => $store]), [
                'domain' => 'shop.example.test',
            ])
            ->assertRedirect(route('teams.stores.show', ['team' => $team, 'store' => $store]));

        $this->assertDatabaseHas('store_domains', [
            'store_id' => $store->id,
            'domain' => 'shop.example.test',
            'status' => 'pending',
        ]);

        $domain = $store->domains()->firstOrFail();

        $this->actingAs($user)
            ->post(route('teams.stores.domains.verification.start', ['team' => $team, 'store' => $store, 'domain' => $domain]))
            ->assertRedirect(route('teams.stores.show', ['team' => $team, 'store' => $store]));

        $domain->refresh();
        $this->assertSame('verifying', $domain->status);
        $this->assertNotNull($domain->verification_token);

        $this->instance(DomainOwnershipVerifier::class, new class implements DomainOwnershipVerifier
        {
            public function verify(string $domain, string $token): bool
            {
                return $domain === 'shop.example.test' && $token !== '';
            }
        });

        $this->actingAs($user)
            ->post(route('teams.stores.domains.verification.check', ['team' => $team, 'store' => $store, 'domain' => $domain]))
            ->assertRedirect(route('teams.stores.show', ['team' => $team, 'store' => $store]));

        $this->assertDatabaseHas('store_domains', [
            'id' => $domain->id,
            'status' => 'verified',
        ]);

        $this->actingAs($user)
            ->patch(route('teams.stores.domains.status', ['team' => $team, 'store' => $store, 'domain' => $domain]), [
                'status' => 'active',
            ])
            ->assertRedirect(route('teams.stores.show', ['team' => $team, 'store' => $store]));

        $this->assertDatabaseHas('store_domains', [
            'id' => $domain->id,
            'status' => 'active',
        ]);
    }

    public function test_team_member_without_update_permission_cannot_manage_domains(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $store = $team->stores()->create(['name' => 'Domain Store', 'slug' => 'domain-store']);

        $this->actingAs($user)
            ->post(route('teams.stores.domains.store', ['team' => $team, 'store' => $store]), [
                'domain' => 'shop.example.test',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('store_domains', ['domain' => 'shop.example.test']);
    }

    public function test_domain_cannot_be_activated_when_dns_txt_is_missing(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $store = $team->stores()->create(['name' => 'Domain Store', 'slug' => 'domain-store']);
        $domain = $store->domains()->create(['domain' => 'shop.example.test', 'status' => 'pending']);

        $this->actingAs($user)
            ->post(route('teams.stores.domains.verification.start', ['team' => $team, 'store' => $store, 'domain' => $domain]));

        $domain->refresh();

        $this->instance(DomainOwnershipVerifier::class, new class implements DomainOwnershipVerifier
        {
            public function verify(string $domain, string $token): bool
            {
                return false;
            }
        });

        $this->actingAs($user)
            ->from(route('teams.stores.show', ['team' => $team, 'store' => $store]))
            ->post(route('teams.stores.domains.verification.check', ['team' => $team, 'store' => $store, 'domain' => $domain]))
            ->assertSessionHasErrors('domain');

        $this->assertDatabaseHas('store_domains', ['id' => $domain->id, 'status' => 'verifying']);
    }
}
