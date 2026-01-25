<?php

namespace Database\Seeders;

use App\Models\Exercise\Exercise;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        $base = [
            'Back Squat' => [],
            'Front Squat' => [],
            'Deadlift Narrow' => [],
            'Deadlift Wide' => [],
            'Row' => [],
            'Bulgarian Split Squat' => [],
        ];

        foreach ($base as $name => $config) {
            Exercise::firstOrCreate([
                'name' => $name,
                'type' => 'strength',
            ]);
        }
    }

    public function runOld()
    {
        Artisan::call('exercise:import');
    }
}
