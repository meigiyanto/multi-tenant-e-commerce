<?php

namespace App\Http\Middleware;

use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMerchantTeamContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $team = $request->route('current_team');

        if (is_string($team)) {
            $team = Team::query()->where('slug', $team)->first();
        }

        abort_unless($team instanceof Team, 404);
        abort_if($team->is_personal && ! $team->stores()->exists(), 403);

        return $next($request);
    }
}
