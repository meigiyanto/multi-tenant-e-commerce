<?php

namespace App\Services\Stores;

use App\Enums\StoreStatus;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreLifecycleService
{
    public function transition(Store $store, StoreStatus $target): Store
    {
        return DB::transaction(function () use ($store, $target): Store {
            $lockedStore = Store::query()->lockForUpdate()->findOrFail($store->getKey());
            $current = StoreStatus::tryFrom($lockedStore->status);

            if ($current === null || ! $current->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => __('The requested Store status transition is not allowed.'),
                ]);
            }

            $lockedStore->status = $target->value;
            $lockedStore->save();

            return $lockedStore;
        });
    }
}
