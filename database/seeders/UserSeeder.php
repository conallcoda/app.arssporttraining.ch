<?php

namespace Database\Seeders;

use App\Models\Users\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create([
            'forename' => 'Admin',
            'surname' => 'User',
            'email' => 'dev@dev.dev',
        ]);

        User::factory()->coach()->count(3)->create();

        $weights = [52, 55, 57, 60, 102, 105, 107.5, 115, 122.5, 52, 55, 57, 60, 102, 105, 107.5, 115, 122.5, 52, 55, 57, 60, 102, 105, 107.5, 115, 122.5, 52, 55, 57, 60, 102, 105, 107.5, 115, 122.5];

        foreach ($weights as $weight) {
            User::factory()->athlete()->create([
                'extra' => [
                    'test_reps' => 1,
                    'test_weight' => $weight,
                    'target_modifier' => 100.0,
                ],
            ]);
        }
    }
}
