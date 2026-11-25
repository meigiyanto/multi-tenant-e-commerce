<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamIndexPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_index_receives_a_plain_array_of_user_teams(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('teams.index'))
            ->assertRedirect(route('merchant.application.show'));
    }
}
