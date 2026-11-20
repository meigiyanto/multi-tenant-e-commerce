<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamIndexPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_index_receives_a_plain_array_of_user_teams(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('teams.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('teams/index')
                ->has('teams', 1)
                ->where('teams.0.name', $user->name."'s Team"),
            );
    }
}
