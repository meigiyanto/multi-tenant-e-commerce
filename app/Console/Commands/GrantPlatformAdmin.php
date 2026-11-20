<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Console\Command;

class GrantPlatformAdmin extends Command
{
    protected $signature = 'platform:admin:grant {email : Email address of the existing user}';

    protected $description = 'Grant Platform Admin access to an existing user';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        PlatformAdmin::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['granted_by_user_id' => null],
        );

        $this->info('Platform Admin access granted to '.$user->email.'.');

        return self::SUCCESS;
    }
}
