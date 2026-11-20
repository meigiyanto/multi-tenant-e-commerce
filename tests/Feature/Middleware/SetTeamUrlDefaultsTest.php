<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\SetTeamUrlDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SetTeamUrlDefaultsTest extends TestCase
{
    public function test_it_clears_stale_team_url_defaults_when_request_has_no_authenticated_user(): void
    {
        URL::defaults([
            'current_team' => 'previous-team',
            'team' => 'previous-team',
        ]);

        $request = Request::create('/');
        $request->setUserResolver(fn () => null);

        (new SetTeamUrlDefaults)->handle($request, fn () => response('ok'));

        $this->assertSame([], URL::getDefaultParameters());
    }
}
