<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Metrics\MetricType;

class MetricSeeder extends Seeder
{
    /**
     * Seed the user database.
     */
    public function run(): void
    {
        $types = [
            'user#athlete' => [
                'Weight' => ['weight', []],
                'Body Fat Percentage' => ['percentage', []],
                'Body Mass Index' => ['number', []],
                '1RM Back Squat' => ['one_rep_max', []],
                '1RM Front Squat' => ['one_rep_max', []],
                '1RM Deadlift' => ['one_rep_max', []],
                '1RM Row' => ['one_rep_max', []],
            ],
            'exercise#strength' => [
                'Reps' => ['number', []],
                'Weight' => ['weight', []],
                'Time Under Tension' => ['time_under_tension', []],
            ],
        ];

        foreach ($types as $modelType => $metrics) {
            [$modelBase, $modelSub] = explode('#', $modelType);
            foreach ($metrics as $name => [$type, $extra]) {
                $factory = MetricType::factory();
                $factory->create([
                    'model_base' => $modelBase,
                    'model_sub' => $modelSub,
                    'name' => $name,
                    'type' => $type,
                    'extra' => $extra,
                ]);
            }
        }
    }
}
