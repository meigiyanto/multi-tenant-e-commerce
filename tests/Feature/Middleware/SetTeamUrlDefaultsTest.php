<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\SetTeamUrlDefaults;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SetTeamUrlDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_defaults_from_the_authenticated_users_current_team(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        (new SetTeamUrlDefaults)->handle($request, fn () => response('ok'));

        $this->assertSame([
            'current_team' => $team->slug,
            'team' => $team->slug,
        ], URL::getDefaultParameters());
    }

    public function test_it_clears_stale_team_url_defaults_when_request_has_no_authenticated_user(): void
    {
        URL::defaults([
            'current_team' => 'previous-team',
            'team' => 'previous-team',
            'locale' => 'id',
        ]);

        $request = Request::create('/');
        $request->setUserResolver(fn () => null);

        (new SetTeamUrlDefaults)->handle($request, fn () => response('ok'));

        $this->assertSame([
            'current_team' => null,
            'team' => null,
            'locale' => 'id',
        ], URL::getDefaultParameters());
    }
}
