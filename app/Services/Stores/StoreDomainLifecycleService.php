<?php

namespace App\Services\Stores;

use App\Enums\StoreDomainStatus;
use App\Models\StoreDomain;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreDomainLifecycleService
{
    public function transition(StoreDomain $domain, StoreDomainStatus $target): StoreDomain
    {
        return DB::transaction(function () use ($domain, $target): StoreDomain {
            $lockedDomain = StoreDomain::query()->whereKey($domain->getKey())->lockForUpdate()->firstOrFail();
            $current = StoreDomainStatus::tryFrom($lockedDomain->status);

            if ($current === null || ! $current->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => __('The requested domain status transition is not allowed.'),
                ]);
            }

            if ($target === StoreDomainStatus::Active && $lockedDomain->verified_at === null) {
                throw ValidationException::withMessages([
                    'status' => __('A domain must be verified before it can be activated.'),
                ]);
            }

            if (in_array($target, [StoreDomainStatus::Pending, StoreDomainStatus::Disabled], true)) {
                $lockedDomain->verified_at = null;
            }

            $lockedDomain->status = $target->value;
            $lockedDomain->save();

            return $lockedDomain;
        });
    }

    /**
     * Mark a domain verified only after a trusted DNS or HTTP ownership check succeeds.
     */
    public function markVerified(StoreDomain $domain): StoreDomain
    {
        return DB::transaction(function () use ($domain): StoreDomain {
            $lockedDomain = StoreDomain::query()->lockForUpdate()->findOrFail($domain->getKey());
            $current = StoreDomainStatus::tryFrom($lockedDomain->status);

            if ($current !== StoreDomainStatus::Verifying) {
                throw ValidationException::withMessages([
                    'status' => __('A domain must be in verification state before it can be verified.'),
                ]);
            }

            $lockedDomain->status = StoreDomainStatus::Verified->value;
            $lockedDomain->verified_at = now();
            $lockedDomain->save();

            return $lockedDomain;
        });
    }
}
