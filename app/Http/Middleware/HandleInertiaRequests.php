<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $platformUser = $request->user();
        $isStorefront = $request->routeIs('storefront.*');
        $storeSubdomain = $request->route('storeSubdomain');
        $customer = $isStorefront && is_string($storeSubdomain) ? $request->user('customer') : null;
        if ($customer !== null && $customer->store?->slug !== $storeSubdomain) {
            $customer = null;
        }
        $user = $isStorefront ? $customer : $platformUser;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'isPlatformAdmin' => ! $isStorefront && ($platformUser?->isPlatformAdmin() ?? false),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => ! $isStorefront && $platformUser?->currentTeam ? $platformUser->toUserTeam($platformUser->currentTeam) : null,
            'teams' => fn () => ! $isStorefront ? ($platformUser?->toUserTeams(includeCurrent: true)->values()->all() ?? []) : [],
        ];
    }
}
