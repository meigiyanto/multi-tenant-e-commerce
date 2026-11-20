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
}
