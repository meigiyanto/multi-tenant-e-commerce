<?php

namespace Tests\Feature\Stores;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StoreManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_members_can_view_their_stores_page(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $team->stores()->create(['name' => 'Main Store', 'slug' => 'main-store']);

        $response = $this->actingAs($user)->get(route('teams.stores.index', $team));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('stores/index')
            ->where('team.slug', $team->slug)
            ->where('canManageStores', true)
            ->has('stores', 1)
            ->where('stores.0.name', 'Main Store'),
        );
    }

    public function test_team_owners_can_create_a_store(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

        $response = $this->actingAs($user)->post(route('teams.stores.store', $team), [
            'name' => 'My Online Store',
            'slug' => 'my-online-store',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);

        $response->assertRedirect(route('teams.stores.index', $team));

        $this->assertDatabaseHas('stores', [
            'team_id' => $team->id,
            'name' => 'My Online Store',
            'slug' => 'my-online-store',
            'status' => 'draft',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);
    }

    public function test_team_members_without_update_permission_cannot_create_stores(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Member->value]);

        $response = $this->actingAs($user)->post(route('teams.stores.store', $team), [
            'name' => 'Blocked Store',
            'slug' => 'blocked-store',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('stores', ['slug' => 'blocked-store']);
    }

    public function test_users_outside_the_team_cannot_view_its_stores(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $response = $this->actingAs($user)->get(route('teams.stores.index', $team));

        $response->assertForbidden();
    }

    public function test_store_slug_must_be_unique_within_the_team(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $team->stores()->create(['name' => 'Existing Store', 'slug' => 'same-slug']);

        $response = $this->actingAs($user)->from(route('teams.stores.index', $team))
            ->post(route('teams.stores.store', $team), [
                'name' => 'Duplicate Store',
                'slug' => 'same-slug',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
            ]);

        $response->assertSessionHasErrors('slug');
        $this->assertDatabaseMissing('stores', ['name' => 'Duplicate Store']);
    }

    public function test_team_members_can_view_store_details_and_domains(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $store = $team->stores()->create(['name' => 'Main Store', 'slug' => 'main-store']);
        $store->domains()->create([
            'domain' => 'shop.example.test',
            'type' => 'custom',
            'status' => 'active',
            'is_primary' => true,
            'verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('teams.stores.show', ['team' => $team, 'store' => $store]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stores/show')
                ->where('team.slug', $team->slug)
                ->where('store.name', 'Main Store')
                ->has('store.domains', 1)
                ->where('store.domains.0.domain', 'shop.example.test')
                ->where('canManageStore', false),
            );
    }

    public function test_team_owners_can_view_and_update_store_settings(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $store = $team->stores()->create([
            'name' => 'Main Store',
            'slug' => 'main-store',
            'settings' => ['checkout' => ['guest_enabled' => true]],
        ]);

        $this->actingAs($user)
            ->get(route('teams.stores.edit', ['team' => $team, 'store' => $store]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stores/settings')
                ->where('store.slug', 'main-store'),
            );

        $this->actingAs($user)
            ->patch(route('teams.stores.update', ['team' => $team, 'store' => $store]), [
                'name' => 'Updated Store',
                'slug' => 'updated-store',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
            ])
            ->assertRedirect(route('teams.stores.show', ['team' => $team, 'store' => $store]));

        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'team_id' => $team->id,
            'name' => 'Updated Store',
            'slug' => 'updated-store',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);

        $this->assertSame(['checkout' => ['guest_enabled' => true]], $store->fresh()->settings);
    }

    public function test_team_members_without_update_permission_cannot_edit_store_settings(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $store = $team->stores()->create(['name' => 'Main Store', 'slug' => 'main-store']);

        $this->actingAs($user)
            ->get(route('teams.stores.edit', ['team' => $team, 'store' => $store]))
            ->assertForbidden();

        $this->actingAs($user)
            ->patch(route('teams.stores.update', ['team' => $team, 'store' => $store]), [
                'name' => 'Changed Store',
                'slug' => 'changed-store',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'name' => 'Main Store']);
    }

    public function test_store_must_belong_to_the_team_in_the_url(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $otherTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $store = $otherTeam->stores()->create(['name' => 'Other Store', 'slug' => 'other-store']);

        $this->actingAs($user)
            ->get(route('teams.stores.show', ['team' => $team, 'store' => $store]))
            ->assertNotFound();
    }

    public function test_store_slug_must_be_unique_within_the_team_when_updated(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $store = $team->stores()->create(['name' => 'First Store', 'slug' => 'first-store']);
        $team->stores()->create(['name' => 'Second Store', 'slug' => 'second-store']);

        $this->actingAs($user)
            ->from(route('teams.stores.edit', ['team' => $team, 'store' => $store]))
            ->patch(route('teams.stores.update', ['team' => $team, 'store' => $store]), [
                'name' => 'First Store',
                'slug' => 'second-store',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
            ])
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'slug' => 'first-store']);
    }

    public function test_team_owner_can_change_store_status(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $store = $team->stores()->create(['name' => 'Draft Store', 'slug' => 'draft-store']);

        $this->actingAs($user)
            ->patch(route('teams.stores.status', ['team' => $team, 'store' => $store]), [
                'status' => 'active',
            ])
            ->assertRedirect(route('teams.stores.show', ['team' => $team, 'store' => $store]));

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'status' => 'active']);
    }

    public function test_team_member_without_update_permission_cannot_change_store_status(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $store = $team->stores()->create(['name' => 'Draft Store', 'slug' => 'draft-store']);

        $this->actingAs($user)
            ->patch(route('teams.stores.status', ['team' => $team, 'store' => $store]), [
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'status' => 'draft']);
    }
}
