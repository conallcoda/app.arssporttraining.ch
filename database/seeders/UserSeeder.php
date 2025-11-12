<?php

namespace Database\Seeders;

use App\Models\Users\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed the user database.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'forename' => 'Admin',
            'surname' => 'User',
            'email' => 'dev@dev.dev',
        ]);

        User::factory()->coach()->create([
            'forename' => 'Coach',
            'surname' => 'User',
            'email' => 'coach@dev.dev',
        ]);

        foreach (range(1, 10) as $i) {
            User::factory()->athlete()->create([
                'email' => "athlete{$i}@dev.dev",
            ]);
        }
    }
}
