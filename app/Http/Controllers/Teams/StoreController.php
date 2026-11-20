<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function index(Team $team): Response
    {
        Gate::authorize('view', $team);

        return Inertia::render('stores/index', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
            ],
            'canManageStores' => Gate::allows('update', $team),
            'stores' => $team->stores()
                ->withCount('domains')
                ->latest()
                ->get()
                ->map(fn (Store $store) => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'slug' => $store->slug,
                    'status' => $store->status,
                    'timezone' => $store->timezone,
                    'currency' => $store->currency,
                    'domains_count' => $store->domains_count,
                    'created_at' => $store->created_at?->toISOString(),
                ]),
        ]);
    }

    public function store(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('stores', 'slug')->where('team_id', $team->id),
            ],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', 'string', 'size:3', 'alpha:upper'],
        ]);

        $team->stores()->create($validated + ['status' => 'draft']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Store created.'),
        ]);

        return to_route('teams.stores.index', ['team' => $team->slug]);
    }
}
