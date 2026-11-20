<?php

namespace App\Http\Controllers\Platform;

use App\Enums\TeamStatus;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\Teams\TeamLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('platform/teams/index', [
            'teams' => Team::query()
                ->withCount(['stores', 'members'])
                ->latest()
                ->paginate(20)
                ->through(fn (Team $team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'slug' => $team->slug,
                    'status' => $team->status,
                    'is_personal' => $team->is_personal,
                    'stores_count' => $team->stores_count,
                    'members_count' => $team->members_count,
                    'created_at' => $team->created_at?->toISOString(),
                ]),
        ]);
    }

    public function updateStatus(Request $request, Team $team, TeamLifecycleService $lifecycle): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(TeamStatus::class)],
        ]);

        $lifecycle->transition($team, TeamStatus::from((string) $validated['status']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team status updated.')]);

        return to_route('platform.admin.teams.index');
    }
}
