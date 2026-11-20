<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamStatus;
use App\Models\Team;
use App\Services\Teams\TeamLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TeamLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_and_new_teams_default_to_active(): void
    {
        $team = Team::factory()->create();

        $this->assertSame(TeamStatus::Active->value, $team->status);
    }

    public function test_active_team_can_be_suspended_and_reactivated(): void
    {
        $team = Team::factory()->create();
        $service = app(TeamLifecycleService::class);

        $service->transition($team, TeamStatus::Suspended);
        $this->assertSame(TeamStatus::Suspended->value, $team->fresh()->status);

        $service->transition($team->fresh(), TeamStatus::Active);
        $this->assertSame(TeamStatus::Active->value, $team->fresh()->status);
    }

    public function test_cancelled_team_cannot_be_reactivated(): void
    {
        $team = Team::factory()->create(['status' => TeamStatus::Cancelled->value]);

        $this->expectException(ValidationException::class);

        app(TeamLifecycleService::class)->transition($team, TeamStatus::Active);
    }
}
