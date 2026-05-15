<?php

namespace App\Support\AthleteMetrics;

use App\Data\Athlete\Metric\Metrics\OneRepMaxMetric;
use App\Data\Exercise\Preview\ExercisePreviewBuilder;
use App\Data\Exercise\Preview\PreviewGrid;
use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Models\Exercise\Exercise;

class OneRepMaxExamplePreviewBuilder
{
    public function build(?OneRepMaxMetric $metric, int|float $targetGoal = 10): ?PreviewGrid
    {
        if (! $metric instanceof OneRepMaxMetric || $metric->measuredReps === null || $metric->measuredWeight === null) {
            return null;
        }

        $config = $this->normalizedExampleConfig($this->exampleConfig());

        $grid = ExercisePreviewBuilder::build(
            data: $config,
            measuredData: new WeightProgressionSetting(
                measuredReps: $metric->measuredReps,
                measuredWeight: $metric->measuredWeight,
                targetGoal: (int) $targetGoal,
            ),
            weeks: 5,
            sessionsPerWeek: 1,
        );

        $grid->showGroupColumn = false;
        $grid->renderGroupColumn = false;
        $grid->showWeekColumn = false;

        return $grid;
    }

    /** @return array<string, mixed> */
    protected function exampleConfig(): array
    {
        $exercise = Exercise::query()
            ->where('name', 'Strength - 1RM 100% Template')
            ->first();

        if ($exercise) {
            return $exercise->config->toArray();
        }

        return [
            'settings' => ['reps', 'weight', 'tempo', 'rest'],
            'overrides' => [
                'sessions' => [],
                'cells' => [],
            ],
            'sets' => [
                'deload' => 'odd',
                'deloadBy' => 1,
                'label' => 'Set',
                'default' => 4,
            ],
            'distance' => null,
            'duration' => null,
            'heartRate' => null,
            'heartRateZone' => null,
            'note' => [
                'default' => 'Stay tall',
                'applyPer' => 'session',
            ],
            'pace' => null,
            'reps' => [
                'mode' => 'automatic',
                'default' => 10,
                'stepDownInterval' => 2,
                'decrement' => 2,
                'minimum' => 1,
                'label' => null,
                'applyPer' => 'session',
            ],
            'rest' => [
                'default' => 60,
                'applyPer' => 'week',
            ],
            'tempo' => [
                'default' => '3010',
                'applyPer' => 'week',
            ],
            'watts' => null,
            'weight' => [
                'mode' => 'automatic',
                'oneRepMaxModifier' => 100,
                'default' => 5,
                'applyPer' => 'session',
            ],
            'preview' => [
                'weeks' => 5,
                'sessionsPerWeek' => 1,
                'measuredReps' => 1,
                'measuredWeight' => 50,
                'targetGoal' => 10,
            ],
        ];
    }

    /** @param  array<string, mixed>  $config */
    protected function normalizedExampleConfig(array $config): array
    {
        $config['settings'] = ['reps', 'weight'];
        $config['preview'] = array_merge($config['preview'] ?? [], [
            'weeks' => 5,
            'sessionsPerWeek' => 1,
            'groupingMode' => SessionGroupingMode::None->value,
            'groupSize' => 1,
            'copyValuesAutomatically' => false,
        ]);

        unset($config['rest'], $config['tempo']);

        return $config;
    }
}
