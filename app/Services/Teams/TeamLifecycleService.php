<?php

namespace App\Services\Teams;

use App\Enums\TeamStatus;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamLifecycleService
{
    public function transition(Team $team, TeamStatus $target): Team
    {
        return DB::transaction(function () use ($team, $target): Team {
            $lockedTeam = Team::query()->lockForUpdate()->findOrFail($team->getKey());
            $current = TeamStatus::tryFrom($lockedTeam->status);

            if ($current === null || ! $current->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => __('The requested Team status transition is not allowed.'),
                ]);
            }

            $lockedTeam->status = $target->value;
            $lockedTeam->save();

            return $lockedTeam;
        });
    }
}
