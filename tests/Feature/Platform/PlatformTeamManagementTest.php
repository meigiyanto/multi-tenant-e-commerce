<?php

namespace Tests\Feature\Platform;

use App\Enums\TeamStatus;
use App\Models\PlatformAdmin;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformTeamManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_platform_admins_can_access_platform_team_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('platform.admin.teams.index'))
            ->assertForbidden();
    }

    public function test_platform_admin_can_view_teams_and_update_team_status(): void
    {
        $admin = User::factory()->create();
        PlatformAdmin::query()->create(['user_id' => $admin->id]);
        $team = Team::factory()->create(['status' => TeamStatus::Active->value]);

        $this->actingAs($admin)
            ->get(route('platform.admin.teams.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/teams/index')
                ->has('teams.data'),
            );

        $this->actingAs($admin)
            ->patch(route('platform.admin.teams.status', $team), ['status' => 'suspended'])
            ->assertRedirect(route('platform.admin.teams.index'));

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'status' => 'suspended']);
    }

    public function test_team_owner_cannot_suspend_their_own_team_through_platform_routes(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => 'owner']);

        $this->actingAs($owner)
            ->patch(route('platform.admin.teams.status', $team), ['status' => 'suspended'])
            ->assertForbidden();

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'status' => 'active']);
    }

    public function test_platform_admin_access_can_be_granted_and_revoked_from_cli(): void
    {
        $user = User::factory()->create();

        $this->artisan('platform:admin:grant', ['email' => $user->email])->assertExitCode(0);
        $this->assertDatabaseHas('platform_admins', ['user_id' => $user->id]);

        $secondAdmin = User::factory()->create();
        PlatformAdmin::query()->create(['user_id' => $secondAdmin->id]);

        $this->artisan('platform:admin:revoke', ['email' => $user->email])->assertExitCode(0);
        $this->assertDatabaseMissing('platform_admins', ['user_id' => $user->id]);
    }
}
