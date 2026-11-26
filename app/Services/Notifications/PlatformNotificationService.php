<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Notifications\Platform\PlatformEventNotification;

class PlatformNotificationService
{
    public function notifyAdmins(array $data): void
    {
        User::query()->whereHas('platformAdmin')->each(fn (User $user) => $user->notify(new PlatformEventNotification($data)));
    }
}
