<?php

namespace Database\Seeders;

use App\Models\Users\User;
use Illuminate\Database\Seeder;

class CoachSeeder extends Seeder
{
    public function run(): void
    {
        $coaches = [
            ['forename' => 'Conall', 'surname' => "O'Reilly", 'email' => 'dev@dev.dev', 'color' => 'blue'],
            ['forename' => 'Armando', 'surname' => 'Stöhr', 'email' => 'armando@dev.dev', 'color' => 'green'],
            ['forename' => 'Sandro', 'surname' => 'Viletta', 'email' => 'sandro@dev.dev', 'color' => 'red'],
            ['forename' => 'Lisa', 'surname' => 'Agerer', 'email' => 'lisa@dev.dev', 'color' => 'purple'],
        ];

        foreach ($coaches as $coach) {
            User::factory()->coach()->create($coach);
        }
    }
}
