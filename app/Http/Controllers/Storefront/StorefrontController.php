<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\Stores\StoreContext;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function __invoke(StoreContext $context): Response
    {
        $store = $context->store();

        abort_if($store === null, 404);

        $settings = $store->settings['storefront'] ?? [];

        return Inertia::render('storefront/index', [
            'store' => [
                'name' => $store->name,
                'slug' => $store->slug,
                'currency' => $store->currency,
                'description' => $settings['description'] ?? '',
                'logo_url' => filled($settings['logo_path'] ?? null) ? Storage::disk('public')->url($settings['logo_path']) : null,
                'banner_url' => filled($settings['banner_path'] ?? null) ? Storage::disk('public')->url($settings['banner_path']) : null,
                'primary_color' => $settings['primary_color'] ?? '#2563eb',
                'accent_color' => $settings['accent_color'] ?? '#0f172a',
                'navigation' => $settings['navigation'] ?? [],
                'contact' => array_merge(['email' => '', 'phone' => '', 'address' => ''], $settings['contact'] ?? []),
            ],
            'domain' => $context->domain()?->domain,
        ]);
    }
}
