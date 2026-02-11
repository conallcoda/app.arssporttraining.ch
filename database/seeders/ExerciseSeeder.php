<?php

namespace Database\Seeders;

use App\Models\Exercise\Exercise;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedStrengthAutomatic();
        $this->seedStrengthManual();
        $this->seedConditioning();
        $this->seedTimed();
        $this->seedCardio();
    }

    protected function seedStrengthAutomatic(): void
    {
        $defaults = [
            'oneRepMaxModifier' => 100,
            'startingReps' => 12,
            'tempo' => '3010',
            'rest' => 30,
        ];

        $exercises = [
            'Back Squat' => [],
            'Front Squat' => ['oneRepMaxModifier' => 85],
            'Deadlift Narrow' => ['oneRepMaxModifier' => 105],
            'Deadlift Wide' => ['oneRepMaxModifier' => 85],
            'Row' => ['oneRepMaxModifier' => 105],
        ];

        foreach ($exercises as $name => $overrides) {
            $exercise = Exercise::firstOrCreate(
                ['name' => $name],
                [
                    'type' => 'strength_automatic',
                    'config' => [
                        'strength_automatic' => array_merge($defaults, $overrides),
                    ],
                ]
            );

            if (in_array($name, ['Back Squat', 'Front Squat'])) {
                $this->attachTags($exercise, [
                    'exercise_category' => ['Squat'],
                    'exercise_equipment' => ['BB'],
                ]);
            }
        }
    }

    protected function seedStrengthManual(): void
    {
        $defaults = [
            'startingWeight' => 0,
            'startingReps' => 12,
            'tempo' => '3010',
            'rest' => 30,
        ];

        $exercises = [
            'Dumbbell Lateral Raise' => ['startingWeight' => 8, 'startingReps' => 15],
            'Cable Face Pull' => ['startingWeight' => 15, 'rest' => 45],
            'Barbell Hip Thrust' => ['startingWeight' => 60, 'startingReps' => 10, 'rest' => 60],
        ];

        foreach ($exercises as $name => $overrides) {
            Exercise::firstOrCreate(
                ['name' => $name],
                [
                    'type' => 'strength_manual',
                    'config' => [
                        'strength_manual' => array_merge($defaults, $overrides),
                    ],
                ]
            );
        }
    }

    protected function seedConditioning(): void
    {
        $repsExercises = [
            'Kettlebell Swings' => ['reps' => 20, 'rest' => 30],
            'Box Jumps' => ['reps' => 10, 'rest' => 45],
        ];

        foreach ($repsExercises as $name => $config) {
            Exercise::firstOrCreate(
                ['name' => $name],
                [
                    'type' => 'conditioning',
                    'config' => [
                        'conditioning' => array_merge(['mode' => 'reps', 'duration' => 0], $config),
                    ],
                ]
            );
        }

        $timeExercises = [
            'Battle Ropes' => ['duration' => 30, 'rest' => 30],
            'Assault Bike Intervals' => ['duration' => 20, 'rest' => 40],
        ];

        foreach ($timeExercises as $name => $config) {
            Exercise::firstOrCreate(
                ['name' => $name],
                [
                    'type' => 'conditioning',
                    'config' => [
                        'conditioning' => array_merge(['mode' => 'time', 'reps' => 0], $config),
                    ],
                ]
            );
        }
    }

    protected function seedTimed(): void
    {
        $exercises = [
            'Plank' => ['duration' => 60, 'rest' => 30],
            'Wall Sit' => ['duration' => 45, 'rest' => 30],
            'Dead Hang' => ['duration' => 30, 'rest' => 60],
        ];

        foreach ($exercises as $name => $config) {
            Exercise::firstOrCreate(
                ['name' => $name],
                [
                    'type' => 'timed',
                    'config' => [
                        'timed' => $config,
                    ],
                ]
            );
        }
    }

    protected function seedCardio(): void
    {
        $distanceExercises = [
            'Treadmill Run' => ['distance' => 5000, 'pace' => '05:00', 'watts' => 0],
            'Rowing' => ['distance' => 2000, 'pace' => '02:00', 'watts' => 150],
        ];

        foreach ($distanceExercises as $name => $config) {
            Exercise::firstOrCreate(
                ['name' => $name],
                [
                    'type' => 'cardio',
                    'config' => [
                        'cardio' => array_merge(['mode' => 'distance', 'duration' => 0], $config),
                    ],
                ]
            );
        }

        $timeExercises = [
            'Steady State Cycling' => ['duration' => 1800, 'pace' => '00:00', 'watts' => 180],
            'Incline Walk' => ['duration' => 1200, 'pace' => '00:00', 'watts' => 0],
        ];

        foreach ($timeExercises as $name => $config) {
            Exercise::firstOrCreate(
                ['name' => $name],
                [
                    'type' => 'cardio',
                    'config' => [
                        'cardio' => array_merge(['mode' => 'time', 'distance' => 0], $config),
                    ],
                ]
            );
        }
    }

    /**
     * @param  array<string, list<string>>  $scopedTags
     */
    protected function attachTags(Exercise $exercise, array $scopedTags): void
    {
        $tagIds = [];

        foreach ($scopedTags as $scope => $names) {
            foreach ($names as $sort => $name) {
                $tag = Tag::firstOrCreate(
                    ['name' => $name, 'scope' => $scope],
                );
                $tagIds[$tag->id] = ['sort' => $sort];
            }
        }

        $exercise->tags()->syncWithoutDetaching($tagIds);
    }

    public function runOld(): void
    {
        Artisan::call('exercise:import');
    }
}
