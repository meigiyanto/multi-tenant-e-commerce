<?php

namespace App\Http\Controllers\Teams;

use App\Contracts\Stores\DomainOwnershipVerifier;
use App\Enums\StoreDomainStatus;
use App\Enums\StoreStatus;
use App\Enums\TeamStatus;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\Team;
use App\Services\Stores\StoreDomainLifecycleService;
use App\Services\Stores\StoreLifecycleService;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'stores' => $team->stores()->withCount(['domains as custom_domains_count' => fn ($query) => $query->where('type', 'custom')])->latest()->get()->map(
                fn (Store $store) => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'slug' => $store->slug,
                    'status' => $store->status,
                    'timezone' => $store->timezone,
                    'currency' => $store->currency,
                    'custom_domains_count' => $store->custom_domains_count,
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

        DB::transaction(function () use ($team, $validated): void {
            $store = $team->stores()->create($validated + ['status' => StoreStatus::Draft->value]);
            $rootDomain = strtolower(trim((string) config('storefront.root_domain', 'localhost'), ". \t\n\r\0\x0B"));

            if (preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $rootDomain) !== 1) {
                throw ValidationException::withMessages([
                    'storefront' => __('The platform storefront root domain is not configured correctly.'),
                ]);
            }

            $storeLabel = rtrim(substr($store->slug, 0, 48), '-');
            $platformDomain = $storeLabel.'-'.$store->getKey().'.'.strtolower($rootDomain);

            $store->domains()->create([
                'domain' => $platformDomain,
                'type' => 'subdomain',
                'is_primary' => true,
                'status' => StoreDomainStatus::Active->value,
                'verified_at' => now(),
            ]);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Store created.')]);

        return to_route('teams.stores.index', ['team' => $team->slug]);
    }

    public function show(Team $team, Store $store): Response
    {
        $this->ensureStoreBelongsToTeam($team, $store);
        Gate::authorize('view', $store);
        $store->load(['domains' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('domain')]);
        $platformDomain = $store->domains->firstWhere('type', 'subdomain');

        return Inertia::render('stores/show', [
            'team' => ['id' => $team->id, 'name' => $team->name, 'slug' => $team->slug, 'status' => $team->status],
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'status' => $store->status,
                'timezone' => $store->timezone,
                'currency' => $store->currency,
                'storefront_url' => $platformDomain === null ? null : config('storefront.scheme', 'https').'://'.$platformDomain->domain,
                'created_at' => $store->created_at?->toISOString(),
                'updated_at' => $store->updated_at?->toISOString(),
                'domains' => $store->domains->map(fn (StoreDomain $domain) => [
                    'id' => $domain->id,
                    'domain' => $domain->domain,
                    'type' => $domain->type,
                    'status' => $domain->status,
                    'is_primary' => $domain->is_primary,
                    'verification_token' => $domain->verification_token,
                    'verified_at' => $domain->verified_at instanceof CarbonInterface ? $domain->verified_at->toISOString() : null,
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

    public function updateStatus(Request $request, Team $team, Store $store, StoreLifecycleService $lifecycle): RedirectResponse
    {
        $this->ensureStoreBelongsToTeam($team, $store);
        Gate::authorize('update', $store);
        $validated = $request->validate(['status' => ['required', Rule::enum(StoreStatus::class)]]);
        $lifecycle->transition($store, StoreStatus::from((string) $validated['status']));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Store status updated.')]);

        return to_route('teams.stores.show', ['team' => $team->slug, 'store' => $store->id]);
    }

    public function addDomain(Request $request, Team $team, Store $store): RedirectResponse
    {
        $this->ensureStoreBelongsToTeam($team, $store);
        Gate::authorize('update', $store);
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}$/i', 'unique:store_domains,domain'],
        ]);
        $store->domains()->create(['domain' => $validated['domain'], 'type' => 'custom', 'status' => StoreDomainStatus::Pending->value]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Domain added. Start verification to receive its DNS challenge.')]);

        return to_route('teams.stores.show', ['team' => $team->slug, 'store' => $store->id]);
    }

    public function startDomainVerification(Team $team, Store $store, StoreDomain $domain, StoreDomainLifecycleService $lifecycle): RedirectResponse
    {
        $this->ensureDomainBelongsToStore($team, $store, $domain);
        Gate::authorize('update', $store);
        $lifecycle->startVerification($domain);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('DNS verification challenge generated.')]);

        return to_route('teams.stores.show', ['team' => $team->slug, 'store' => $store->id]);
    }

    public function verifyDomain(Team $team, Store $store, StoreDomain $domain, DomainOwnershipVerifier $verifier, StoreDomainLifecycleService $lifecycle): RedirectResponse
    {
        $this->ensureDomainBelongsToStore($team, $store, $domain);
        Gate::authorize('update', $store);
        abort_unless($domain->status === StoreDomainStatus::Verifying->value && $domain->verification_token !== null, 422);

        if (! $verifier->verify($domain->domain, $domain->verification_token)) {
            throw ValidationException::withMessages(['domain' => __('The expected DNS TXT record was not found. DNS changes may take time to propagate.')]);
        }

        $lifecycle->markVerified($domain);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Domain ownership verified. You can now activate it.')]);

        return to_route('teams.stores.show', ['team' => $team->slug, 'store' => $store->id]);
    }

    public function updateDomainStatus(Request $request, Team $team, Store $store, StoreDomain $domain, StoreDomainLifecycleService $lifecycle): RedirectResponse
    {
        $this->ensureDomainBelongsToStore($team, $store, $domain);
        Gate::authorize('update', $store);
        $validated = $request->validate(['status' => ['required', Rule::enum(StoreDomainStatus::class)]]);
        $lifecycle->transition($domain, StoreDomainStatus::from((string) $validated['status']));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Domain status updated.')]);

        return to_route('teams.stores.show', ['team' => $team->slug, 'store' => $store->id]);
    }

    public function update(Request $request, Team $team, Store $store): RedirectResponse
    {
        $this->ensureStoreBelongsToTeam($team, $store);
        Gate::authorize('update', $store);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('stores', 'slug')->where('team_id', $team->id)->ignore($store->id)],
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

    private function ensureDomainBelongsToStore(Team $team, Store $store, StoreDomain $domain): void
    {
        $this->ensureStoreBelongsToTeam($team, $store);
        abort_unless($domain->store_id === $store->id, 404);
    }
}
