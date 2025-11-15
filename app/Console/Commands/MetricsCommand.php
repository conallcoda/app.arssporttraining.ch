<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Metrics\MetricType;

class MetricsCommand extends Command
{
    protected $signature = 'metrics';


    public function handle()
    {
        $types = [
            'athlete' => [
                'Weight' => ['weight', ['step' => 0.1]],
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
        return 0;
    }
}
