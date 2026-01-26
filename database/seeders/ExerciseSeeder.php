<?php

namespace Database\Seeders;

use App\Models\Exercise\Exercise;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        $strengthDefaults = [
            'oneRepMaxModifier' => 100,
            'startingReps' => 12,
            'timeUnderTension' => '03-01-03-01',
            'rest' => 30,
        ];

        $exercises = [
            'Back Squat' => [],
            'Front Squat' => ['oneRepMaxModifier' => 85],
            'Deadlift Narrow' => ['oneRepMaxModifier' => 85],
            'Deadlift Wide' => ['oneRepMaxModifier' => 85],
            'Row' => ['oneRepMaxModifier' => 70],
        ];

        foreach ($exercises as $name => $overrides) {
            Exercise::firstOrCreate(
                ['name' => $name],
                [
                    'type' => 'strength',
                    'extra' => [
                        'type' => array_merge($strengthDefaults, $overrides),
                    ],
                ]
            );
        }
    }

    public function runOld()
    {
        Artisan::call('exercise:import');
    }
}
