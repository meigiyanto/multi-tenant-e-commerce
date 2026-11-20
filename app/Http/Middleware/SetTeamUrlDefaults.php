<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetTeamUrlDefaults
{
    /**
     * Set or clear the default URL parameters for team-based routes.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $currentTeam = $request->user()?->currentTeam;

        URL::defaults($currentTeam ? [
            'current_team' => $currentTeam->slug,
            'team' => $currentTeam->slug,
        ] : []);

        return $next($request);
    }
}
