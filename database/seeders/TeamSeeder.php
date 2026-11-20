<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Enums\TeamStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Seed a demo business Team and its owner without creating duplicates.
     */
    public function run(): void
    {
        $owner = User::query()->where('email', 'test@example.com')->first()
            ?? User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $team = Team::query()->firstOrCreate(
            ['slug' => 'demo-commerce-team'],
            [
                'name' => 'Demo Commerce Team',
                'is_personal' => false,
                'status' => TeamStatus::Active->value,
            ],
        );

        $team->members()->syncWithoutDetaching([
            $owner->id => ['role' => TeamRole::Owner->value],
        ]);
    }
}
