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
            'athlete' => [
                'Weight' => ['weight', ['step' => 0.1]],
                'Body Fat Percentage' => ['percentage', ['step' => 0.1]],
                'Body Mass Index' => ['number', ['step' => 0.1]],
                '1RM Back Squat' => ['one_rep_max', ['multiplier' => 1]],
                '1RM Front Squat' => ['one_rep_max', ['multiplier' => 0.85]],
                '1RM Deadlift Wide' => ['one_rep_max', ['multiplier' => 0.85]],
                '1RM Deadlift Narrow' => ['one_rep_max', ['multiplier' => 0.85]],
                '1RM Row' => ['one_rep_max', ['multiplier' => 1]],
            ],
        ];

        foreach ($types as $scope => $metrics) {
            foreach ($metrics as $name => [$type, $config]) {
                $model = MetricType::getMetricTypeModel($scope, $type);
                $config = $model::from($config)->toArray();
                MetricType::create([
                    'scope' => $scope,
                    'label' => $name,
                    'type' => $type,
                    'config' => $config,
                ]);
            }
        }
    }
}
