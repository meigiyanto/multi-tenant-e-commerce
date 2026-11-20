<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Console\Command;

class RevokePlatformAdmin extends Command
{
    protected $signature = 'platform:admin:revoke {email : Email address of the existing user}';

    protected $description = 'Revoke Platform Admin access from a user';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $deleted = PlatformAdmin::query()->where('user_id', $user->id)->delete();

        $this->info($deleted > 0 ? 'Platform Admin access revoked.' : 'User was not a Platform Admin.');

        return self::SUCCESS;
    }
}
