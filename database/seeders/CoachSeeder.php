<?php

namespace Database\Seeders;

use App\Models\Users\User;
use Illuminate\Database\Seeder;

class CoachSeeder extends Seeder
{
    public function run(): void
    {
        $coaches = [
            ['forename' => 'Conall', 'surname' => "O'Reilly", 'email' => 'conall@coda.works', 'color' => 'blue'],
            ['forename' => 'Armando', 'surname' => 'Stöhr', 'email' => 'armando.stoehr@arssporttraining.com', 'color' => 'green'],
            ['forename' => 'Sandro', 'surname' => 'Viletta', 'email' => 'viletta.sandro@bluewin.ch', 'color' => 'red'],
            ['forename' => 'Lisa', 'surname' => 'Agerer', 'email' => 'lisam.agerer@gmail.com', 'color' => 'purple'],
        ];

        foreach ($coaches as $coach) {
            User::factory()->coach()->create($coach);
        }
    }
}
