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
use App\Services\Stores\PlatformStorefrontDomainService;
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
                fn (Store $store): array => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'slug' => $store->slug,
                    'status' => $store->status,
                    'timezone' => $store->timezone,
                    'currency' => $store->currency,
                    'custom_domains_count' => (int) $store->getAttribute('custom_domains_count'),
                    'created_at' => $store->created_at?->toISOString(),
                ],
            ),
        ]);
    }

    public function store(Request $request, Team $team, PlatformStorefrontDomainService $platformDomains): RedirectResponse
    {
        Gate::authorize('update', $team);
        abort_unless($team->status === TeamStatus::Active->value, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('stores', 'slug')->where('team_id', $team->id)],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', 'string', 'size:3', 'alpha:upper'],
        ]);

        DB::transaction(function () use ($team, $validated, $platformDomains): void {
            $store = $team->stores()->create($validated + ['status' => StoreStatus::Draft->value]);
            $platformDomains->ensureFor($store);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Store created.')]);

        return to_route('teams.stores.index', ['team' => $team->slug]);
    }

    public function show(Team $team, Store $store, StoreDomainLifecycleService $domainLifecycle): Response
    {
        $this->ensureStoreBelongsToTeam($team, $store);
        Gate::authorize('view', $store);
        $store->load(['domains' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('domain')]);
        foreach ($store->domains as $domain) {
            if ($domain->status === StoreDomainStatus::Verifying->value) {
                $domainLifecycle->expireIfOverdue($domain);
            }
        }
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
                    'verification_expires_at' => $domain->verification_expires_at?->toISOString(),
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

        $storefront = $store->settings['storefront'] ?? [];

        return Inertia::render('stores/settings', [
            'team' => ['id' => $team->id, 'name' => $team->name, 'slug' => $team->slug, 'status' => $team->status],
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'status' => $store->status,
                'timezone' => $store->timezone,
                'currency' => $store->currency,
                'storefront' => [
                    'description' => $storefront['description'] ?? '',
                    'logo_url' => filled($storefront['logo_path'] ?? null) ? Storage::disk('public')->url($storefront['logo_path']) : null,
                    'banner_url' => filled($storefront['banner_path'] ?? null) ? Storage::disk('public')->url($storefront['banner_path']) : null,
                    'primary_color' => $storefront['primary_color'] ?? '#2563eb',
                    'accent_color' => $storefront['accent_color'] ?? '#0f172a',
                    'navigation' => $storefront['navigation'] ?? [],
                    'contact' => array_merge(['email' => '', 'phone' => '', 'address' => ''], $storefront['contact'] ?? []),
                ],
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

        if ($lifecycle->expireIfOverdue($domain)) {
            throw ValidationException::withMessages(['domain' => __('The DNS verification challenge has expired. Generate a new challenge to continue.')]);
        }

        $domain->refresh();

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
            'description' => ['nullable', 'string', 'max:1000'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'navigation' => ['nullable', 'array', 'max:8'],
            'navigation.*.label' => ['required_with:navigation.*.url', 'nullable', 'string', 'max:40'],
            'navigation.*.url' => ['required_with:navigation.*.label', 'nullable', 'string', 'max:255', 'regex:#^(https://|/(?!/))[^\\s]+$#i'],
            'contact.email' => ['nullable', 'email', 'max:255'],
            'contact.phone' => ['nullable', 'string', 'max:40'],
            'contact.address' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_banner' => ['sometimes', 'boolean'],
        ]);
        $settings = $store->settings ?? [];
        $storefront = $settings['storefront'] ?? [];
        $oldFiles = [];
        foreach (['logo' => 'logo_path', 'banner' => 'banner_path'] as $field => $key) {
            if ($request->boolean('remove_'.$field) && filled($storefront[$key] ?? null)) {
                $oldFiles[] = $storefront[$key];
                $storefront[$key] = null;
            }
            if ($request->hasFile($field)) {
                if (filled($storefront[$key] ?? null)) {
                    $oldFiles[] = $storefront[$key];
                }
                $storefront[$key] = $request->file($field)->store("stores/{$store->id}/storefront", 'public');
            }
        }
        $storefront = array_merge($storefront, [
            'description' => $validated['description'] ?? '',
            'primary_color' => $validated['primary_color'],
            'accent_color' => $validated['accent_color'],
            'navigation' => collect($validated['navigation'] ?? [])->filter(fn (array $item) => filled($item['label'] ?? null) && filled($item['url'] ?? null))->values()->all(),
            'contact' => [
                'email' => $validated['contact']['email'] ?? '',
                'phone' => $validated['contact']['phone'] ?? '',
                'address' => $validated['contact']['address'] ?? '',
            ],
        ]);
        unset($validated['description'], $validated['primary_color'], $validated['accent_color'], $validated['navigation'], $validated['contact'], $validated['logo'], $validated['banner'], $validated['remove_logo'], $validated['remove_banner']);
        $settings['storefront'] = $storefront;
        $store->update($validated + ['settings' => $settings]);
        foreach ($oldFiles as $oldFile) {
            Storage::disk('public')->delete($oldFile);
        }
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
