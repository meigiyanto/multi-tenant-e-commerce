<?php

namespace App\Services\Stores;

use App\Models\StoreDomain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class StoreDomainService
{
    public function resolve(string $host): ?StoreDomain
    {
        $domain = $this->normalizeHost($host);

        if ($domain === '') {
            return null;
        }

        return StoreDomain::query()
            ->with(['store.team'])
            ->where('domain', $domain)
            ->where('status', 'active')
            ->whereNotNull('verified_at')
            ->whereHas('store', function (Builder $query): void {
                $query->where('status', 'active')
                    ->whereHas('team');
            })
            ->first();
    }

    public function normalizeHost(string $host): string
    {
        return rtrim(Str::lower(trim($host)), '.');
    }
}
