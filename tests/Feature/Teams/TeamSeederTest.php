<?php

namespace Tests\Feature\Teams;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_seeder_creates_a_business_team_with_an_owner(): void
    {
        $this->seed(TeamSeeder::class);

        $owner = User::query()->where('email', 'test@example.com')->firstOrFail();
        $team = Team::query()->where('slug', 'demo-commerce-team')->firstOrFail();

        $this->assertFalse($team->is_personal);
        $this->assertTrue($team->members()->whereKey($owner->id)->exists());
        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
    }

    public function test_team_seeder_is_safe_to_run_more_than_once(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(TeamSeeder::class);

        $owner = User::query()->where('email', 'test@example.com')->firstOrFail();
        $team = Team::query()->where('slug', 'demo-commerce-team')->firstOrFail();

        $this->assertSame(1, Team::query()->where('slug', 'demo-commerce-team')->count());
        $this->assertSame(1, $team->memberships()->where('user_id', $owner->id)->count());
    }
}
