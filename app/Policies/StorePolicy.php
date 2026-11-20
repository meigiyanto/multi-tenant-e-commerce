<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    public function view(User $user, Store $store): bool
    {
        $team = $store->team;

        return $team !== null && $user->belongsToTeam($team);
    }

    public function update(User $user, Store $store): bool
    {
        $team = $store->team;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::UpdateTeam);
    }
}
