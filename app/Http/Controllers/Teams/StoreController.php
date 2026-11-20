<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamStatus;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            'team' => ['id' => $team->id, 'name' => $team->name, 'slug' => $team->slug, 'status' => $team->status],
            'canManageStores' => Gate::allows('update', $team),
            'stores' => $team->stores()->withCount('domains')->latest()->get()->map(
                fn (Store $store) => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'slug' => $store->slug,
                    'status' => $store->status,
                    'timezone' => $store->timezone,
                    'currency' => $store->currency,
                    'domains_count' => $store->domains_count,
                    'created_at' => $store->created_at?->toISOString(),
                ],
            ),
        ]);
    }

    public function store(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);
        abort_unless($team->status === TeamStatus::Active->value, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('stores', 'slug')->where('team_id', $team->id)],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', 'string', 'size:3', 'alpha:upper'],
        ]);

        $team->stores()->create($validated + ['status' => 'draft']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Store created.')]);

        return to_route('teams.stores.index', ['team' => $team->slug]);
    }

    public function show(Team $team, Store $store): Response
    {
        $this->ensureStoreBelongsToTeam($team, $store);
        Gate::authorize('view', $store);

        $store->load(['domains' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('domain')]);

        return Inertia::render('stores/show', [
            'team' => ['id' => $team->id, 'name' => $team->name, 'slug' => $team->slug, 'status' => $team->status],
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'status' => $store->status,
                'timezone' => $store->timezone,
                'currency' => $store->currency,
                'created_at' => $store->created_at?->toISOString(),
                'updated_at' => $store->updated_at?->toISOString(),
                'domains' => $store->domains->map(fn (StoreDomain $domain) => [
                    'id' => $domain->id,
                    'domain' => $domain->domain,
                    'type' => $domain->type,
                    'status' => $domain->status,
                    'is_primary' => $domain->is_primary,
                    'verified_at' => $domain->verified_at instanceof Carbon
                        ? $domain->verified_at->toISOString()
                        : null,
                ]),
            ],
            'canManageStore' => Gate::allows('update', $store),
        ]);
    }

    public function edit(Team $team, Store $store): Response
    {
        $this->ensureStoreBelongsToTeam($team, $store);
        Gate::authorize('update', $store);

        return Inertia::render('stores/settings', [
            'team' => ['id' => $team->id, 'name' => $team->name, 'slug' => $team->slug, 'status' => $team->status],
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'status' => $store->status,
                'timezone' => $store->timezone,
                'currency' => $store->currency,
            ],
        ]);
    }

    public function update(Request $request, Team $team, Store $store): RedirectResponse
    {
        $this->ensureStoreBelongsToTeam($team, $store);
        Gate::authorize('update', $store);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('stores', 'slug')->where('team_id', $team->id)->ignore($store->id),
            ],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', 'string', 'size:3', 'alpha:upper'],
        ]);

        $store->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Store settings updated.')]);

        return to_route('teams.stores.show', ['team' => $team->slug, 'store' => $store->id]);
    }

    private function ensureStoreBelongsToTeam(Team $team, Store $store): void
    {
        abort_unless($store->team_id === $team->id, 404);
    }
}
