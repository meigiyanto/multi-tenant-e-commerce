<?php

namespace App\Services\Stores;

use App\Enums\StoreDomainStatus;
use App\Models\StoreDomain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

            if (in_array($target, [StoreDomainStatus::Pending, StoreDomainStatus::Disabled, StoreDomainStatus::Expired], true)) {
                $lockedDomain->verified_at = null;
                $lockedDomain->verification_token = null;
                $lockedDomain->verification_expires_at = null;
            }

            $lockedDomain->status = $target->value;
            $lockedDomain->save();

            return $lockedDomain;
        });
    }

    public function startVerification(StoreDomain $domain): StoreDomain
    {
        return DB::transaction(function () use ($domain): StoreDomain {
            $lockedDomain = StoreDomain::query()->whereKey($domain->getKey())->lockForUpdate()->firstOrFail();
            $current = StoreDomainStatus::tryFrom($lockedDomain->status);

            if (! in_array($current, [StoreDomainStatus::Pending, StoreDomainStatus::Expired], true)) {
                throw ValidationException::withMessages([
                    'status' => __('Only pending or expired domains can start verification.'),
                ]);
            }

            $lockedDomain->status = StoreDomainStatus::Verifying->value;
            $lockedDomain->verified_at = null;
            $lockedDomain->verification_token = Str::random(48);
            $lockedDomain->verification_expires_at = now()->addHours(
                (int) config('storefront.domain_verification_hours', 72),
            );
            $lockedDomain->save();

            return $lockedDomain;
        });
    }

    /**
     * Expire a challenge atomically when a verification request reaches the application.
     */
    public function expireIfOverdue(StoreDomain $domain): bool
    {
        return DB::transaction(function () use ($domain): bool {
            $lockedDomain = StoreDomain::query()->whereKey($domain->getKey())->lockForUpdate()->firstOrFail();

            if (
                $lockedDomain->status !== StoreDomainStatus::Verifying->value
                || $lockedDomain->verification_expires_at === null
                || $lockedDomain->verification_expires_at->isFuture()
            ) {
                return false;
            }

            $lockedDomain->status = StoreDomainStatus::Expired->value;
            $lockedDomain->verification_token = null;
            $lockedDomain->save();

            return true;
        });
    }

    /**
     * This trusted operation must only be called after DomainOwnershipVerifier succeeds.
     */
    public function markVerified(StoreDomain $domain): StoreDomain
    {
        return DB::transaction(function () use ($domain): StoreDomain {
            $lockedDomain = StoreDomain::query()->whereKey($domain->getKey())->lockForUpdate()->firstOrFail();
            $current = StoreDomainStatus::tryFrom($lockedDomain->status);

            if (
                $current !== StoreDomainStatus::Verifying
                || $lockedDomain->verification_token === null
                || $lockedDomain->verification_expires_at === null
                || $lockedDomain->verification_expires_at->isPast()
            ) {
                throw ValidationException::withMessages([
                    'status' => __('The domain verification challenge is missing or has expired. Generate a new challenge to continue.'),
                ]);
            }

            $lockedDomain->status = StoreDomainStatus::Verified->value;
            $lockedDomain->verified_at = now();
            $lockedDomain->verification_token = null;
            $lockedDomain->verification_expires_at = null;
            $lockedDomain->save();

            return $lockedDomain;
        });
    }
}
