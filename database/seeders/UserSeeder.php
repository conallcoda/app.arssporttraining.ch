<?php

namespace Database\Seeders;

use App\Models\Users\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'forename' => 'Admin',
            'surname' => 'User',
            'email' => 'dev@dev.dev',
        ]);

        User::factory()->count(10)->create();
    }
}
