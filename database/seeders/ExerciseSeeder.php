<?php

namespace Database\Seeders;

use App\Models\Exercise\Exercise;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedExercises();
    }

    protected function seedExercises(): void
    {
        $exercises = [
            ...self::strengthExercises(),
            ...self::conditioningExercises(),
            ...self::durationExercises(),
            ...self::cardioExercises(),
        ];

        foreach ($exercises as $name => $config) {
            $exercise = Exercise::firstOrCreate(
                ['name' => $name],
                ['config' => $config]
            );

            if (in_array($name, ['Back Squat', 'Front Squat'])) {
                $category = Tag::firstOrCreate(['name' => 'Squat', 'scope' => 'exercise_category']);
                $exercise->update(['category_id' => $category->id]);

                $this->attachTags($exercise, [
                    'exercise_equipment' => ['BB'],
                ]);
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    private static function strengthExercises(): array
    {
        $autoBase = [
            'settings' => ['reps', 'weight', 'tempo', 'rest'],
            'sets' => ['default' => 4, 'deload' => 'none', 'deloadBy' => 1, 'label' => 'Set'],
            'reps' => ['mode' => 'auto', 'default' => 8, 'stepDownInterval' => 2, 'decrement' => 2, 'minimum' => 4, 'applyPer' => 'session'],
            'weight' => ['mode' => 'auto', 'oneRepMaxModifier' => 100, 'default' => 0, 'applyPer' => 'session'],
            'tempo' => ['default' => '3010', 'applyPer' => 'session'],
            'rest' => ['default' => 30, 'applyPer' => 'session'],
            'overrides' => ['cells' => [], 'weeks' => []],
        ];

        return [
            'Back Squat' => $autoBase,
            'Front Squat' => array_replace_recursive($autoBase, [
                'weight' => ['oneRepMaxModifier' => 85],
            ]),
            'Deadlift Narrow' => array_replace_recursive($autoBase, [
                'weight' => ['oneRepMaxModifier' => 105],
            ]),
            'Row' => array_replace_recursive($autoBase, [
                'weight' => ['oneRepMaxModifier' => 105],
            ]),
            'Dumbbell Lateral Raise' => [
                'settings' => ['reps', 'weight', 'tempo', 'rest'],
                'sets' => ['default' => 3, 'deload' => 'none', 'deloadBy' => 1, 'label' => 'Set'],
                'reps' => ['mode' => 'manual', 'default' => 15, 'applyPer' => 'session'],
                'weight' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
                'tempo' => ['default' => '3010', 'applyPer' => 'session'],
                'rest' => ['default' => 30, 'applyPer' => 'session'],
                'overrides' => ['cells' => [], 'weeks' => []],
            ],
            'Cable Face Pull' => [
                'settings' => ['reps', 'weight', 'tempo', 'rest'],
                'sets' => ['default' => 3, 'deload' => 'none', 'deloadBy' => 1, 'label' => 'Set'],
                'reps' => ['mode' => 'manual', 'default' => 15, 'applyPer' => 'session'],
                'weight' => ['mode' => 'manual', 'default' => 15, 'applyPer' => 'session'],
                'tempo' => ['default' => '3010', 'applyPer' => 'session'],
                'rest' => ['default' => 45, 'applyPer' => 'session'],
                'overrides' => ['cells' => [], 'weeks' => []],
            ],
            'Barbell Hip Thrust' => [
                'settings' => ['reps', 'weight', 'tempo', 'rest'],
                'sets' => ['default' => 4, 'deload' => 'none', 'deloadBy' => 1, 'label' => 'Set'],
                'reps' => ['mode' => 'manual', 'default' => 10, 'applyPer' => 'session'],
                'weight' => ['mode' => 'manual', 'default' => 60, 'applyPer' => 'session'],
                'tempo' => ['default' => '3010', 'applyPer' => 'session'],
                'rest' => ['default' => 60, 'applyPer' => 'session'],
                'overrides' => ['cells' => [], 'weeks' => []],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private static function conditioningExercises(): array
    {
        return [
            'Kettlebell Swings' => [
                'settings' => ['reps', 'rest'],
                'sets' => ['default' => 3, 'deload' => 'none', 'deloadBy' => 1, 'label' => 'Set'],
                'reps' => ['mode' => 'manual', 'default' => 20, 'applyPer' => 'session'],
                'rest' => ['default' => 30, 'applyPer' => 'session'],
                'overrides' => ['cells' => [], 'weeks' => []],
            ],
            'Box Jumps' => [
                'settings' => ['reps', 'rest'],
                'sets' => ['default' => 3, 'deload' => 'none', 'deloadBy' => 1, 'label' => 'Set'],
                'reps' => ['mode' => 'manual', 'default' => 10, 'applyPer' => 'session'],
                'rest' => ['default' => 45, 'applyPer' => 'session'],
                'overrides' => ['cells' => [], 'weeks' => []],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private static function durationExercises(): array
    {
        $base = [
            'settings' => ['duration', 'rest'],
            'sets' => ['default' => 3, 'deload' => 'none', 'deloadBy' => 1, 'label' => 'Set'],
            'duration' => ['unit' => 'seconds', 'default' => 30],
            'rest' => ['default' => 30, 'applyPer' => 'session'],
            'overrides' => ['cells' => [], 'weeks' => []],
        ];

        return [
            'Battle Ropes' => $base,
            'Plank' => array_replace_recursive($base, [
                'duration' => ['default' => 60],
            ]),
            'Wall Sit' => array_replace_recursive($base, [
                'duration' => ['default' => 45],
            ]),
            'Dead Hang' => array_replace_recursive($base, [
                'duration' => ['default' => 30],
                'rest' => ['default' => 60],
            ]),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private static function cardioExercises(): array
    {
        return [
            'Treadmill Run' => [
                'settings' => ['distance', 'pace'],
                'sets' => ['default' => 1, 'deload' => 'none', 'deloadBy' => 1, 'label' => 'Set'],
                'distance' => ['unit' => 'meters', 'default' => 5000],
                'pace' => ['default' => '05:00'],
                'overrides' => ['cells' => [], 'weeks' => []],
            ],
            'Rowing' => [
                'settings' => ['distance', 'pace'],
                'sets' => ['default' => 1, 'deload' => 'none', 'deloadBy' => 1, 'label' => 'Set'],
                'distance' => ['unit' => 'meters', 'default' => 2000],
                'pace' => ['default' => '02:00'],
                'overrides' => ['cells' => [], 'weeks' => []],
            ],
        ];
    }

    /** @param array<string, list<string>> $scopedTags */
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
}
