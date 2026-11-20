<?php

namespace App\Services\Stores;

use App\Models\Store;
use App\Models\StoreDomain;
use Illuminate\Validation\ValidationException;

class PlatformStorefrontDomainService
{
    public function ensureFor(Store $store): StoreDomain
    {
        $domain = $store->domains()->where('type', 'subdomain')->first();

        if ($domain !== null) {
            if ($domain->status !== 'active' || $domain->verified_at === null) {
                $domain->forceFill([
                    'status' => 'active',
                    'verified_at' => now(),
                    'verification_token' => null,
                ])->save();
            }

            return $domain->refresh();
        }

        $rootDomain = strtolower(trim((string) config('storefront.root_domain', 'localhost'), ". \t\n\r\0\x0B"));

        if (preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $rootDomain) !== 1) {
            throw ValidationException::withMessages([
                'storefront' => __('The platform storefront root domain is not configured correctly.'),
            ]);
        }

        $storeLabel = rtrim(substr($store->slug, 0, 48), '-');
        $platformDomain = $storeLabel.'-'.$store->getKey().'.'.$rootDomain;

        return $store->domains()->create([
            'domain' => $platformDomain,
            'type' => 'subdomain',
            'is_primary' => ! $store->domains()->where('is_primary', true)->exists(),
            'status' => 'active',
            'verified_at' => now(),
        ]);
    }
}
