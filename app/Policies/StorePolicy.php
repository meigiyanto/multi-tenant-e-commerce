<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    public function view(User $user, Store $store): bool
    {
        return $user->belongsToTeam($store->team);
    }

    public function update(User $user, Store $store): bool
    {
        return $user->hasTeamPermission($store->team, TeamPermission::UpdateTeam);
    }
}
