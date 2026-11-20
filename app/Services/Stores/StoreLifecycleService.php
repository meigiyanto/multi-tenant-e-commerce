<?php

namespace App\Services\Stores;

use App\Enums\StoreStatus;
use App\Enums\TeamStatus;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreLifecycleService
{
    public function transition(Store $store, StoreStatus $target): Store
    {
        return DB::transaction(function () use ($store, $target): Store {
            $lockedStore = Store::query()->with('team')->lockForUpdate()->findOrFail($store->getKey());
            $current = StoreStatus::tryFrom($lockedStore->status);

            if ($current === null || ! $current->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => __('The requested Store status transition is not allowed.'),
                ]);
            }

            if ($target === StoreStatus::Active && $lockedStore->team?->status !== TeamStatus::Active->value) {
                throw ValidationException::withMessages([
                    'status' => __('A Store can only be activated while its Team is active.'),
                ]);
            }

            $lockedStore->status = $target->value;
            $lockedStore->save();

            return $lockedStore;
        });
    }
}
