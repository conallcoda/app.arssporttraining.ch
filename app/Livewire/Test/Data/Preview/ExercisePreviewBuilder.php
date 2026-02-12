<?php

namespace App\Livewire\Test\Data\Preview;

use App\Livewire\Test\Data\ExerciseSetting;
use App\Livewire\Test\Data\Strategies\Weight\MeasuredData;

class ExercisePreviewBuilder
{
    private const DEFAULT_WEEKS = 5;

    private const PRIORITY = [
        'reps',
        'weight',
        'distance',
        'duration',
        'pace',
        'watts',
        'tempo',
        'rest',
    ];

    private const ROW_COLORS = [
        'bg-blue-50 dark:bg-blue-900/20',
        'bg-green-50 dark:bg-green-900/20',
        'bg-red-50 dark:bg-red-900/20',
        'bg-purple-50 dark:bg-purple-900/20',
        'bg-cyan-50 dark:bg-cyan-900/20',
        'bg-orange-50 dark:bg-orange-900/20',
        'bg-pink-50 dark:bg-pink-900/20',
        'bg-amber-50 dark:bg-amber-900/20',
    ];

    private const ONE_REP_MAX_COLOR = 'bg-orange-50 dark:bg-orange-900/20';

    public static function build(array $data, ?MeasuredData $measuredData = null, int $weeks = self::DEFAULT_WEEKS): PreviewGrid
    {
        $orchestrator = new StrategyOrchestrator($data, $measuredData, $weeks);
        $state = $orchestrator->execute();

        $settings = $data['settings'] ?? [];

        usort($settings, function (string $a, string $b) {
            $priorityA = array_search($a, self::PRIORITY);
            $priorityB = array_search($b, self::PRIORITY);

            return ($priorityA === false ? PHP_INT_MAX : $priorityA)
                <=> ($priorityB === false ? PHP_INT_MAX : $priorityB);
        });

        $rows = [];
        $weekColumns = [];
        $colorIndex = 0;
        $setsPerWeek = $state->getSetsPerWeek();

        foreach ($settings as $setting) {
            $config = $data[$setting] ?? [];
            $applyPer = $config['applyPer'] ?? 'session';

            if ($applyPer === 'week') {
                $weekColumns[] = self::buildWeekColumn($setting, $config, $weeks);

                continue;
            }

            $color = self::ROW_COLORS[$colorIndex] ?? 'bg-zinc-50 dark:bg-zinc-800/20';
            $colorIndex++;

            if ($setting === 'weight') {
                $weightRows = self::buildWeightRows($config, $color, $weeks, $setsPerWeek, $state);
                foreach ($weightRows as $row) {
                    $rows[] = $row;
                }
            } else {
                $rows[] = self::buildRow($setting, $config, $color, $weeks, $setsPerWeek, $state);
            }
        }

        return new PreviewGrid(
            rows: $rows,
            weekCount: $weeks,
            setCount: $state->maxSets(),
            setLabel: ($data['sets']['label'] ?? 'Set'),
            weekColumns: $weekColumns,
            summary: $state->getMetadata('weight', 'summary'),
        );
    }

    /** @param array<int, int> $setsPerWeek */
    private static function buildRow(string $setting, array $config, string $color, int $weeks, array $setsPerWeek, GridState $state): PreviewGridRow
    {
        $cells = $state->hasGrid($setting)
            ? $state->getGrid($setting)
            : self::buildDefaultCells($config, $weeks, $setsPerWeek);

        return new PreviewGridRow(
            field: $setting,
            label: self::resolveLabel($setting, $config),
            color: $color,
            cells: $cells,
        );
    }

    /**
     * @param  array<int, int>  $setsPerWeek
     * @return PreviewGridRow[]
     */
    private static function buildWeightRows(array $config, string $color, int $weeks, array $setsPerWeek, GridState $state): array
    {
        $rows = [];

        if ($state->hasGrid('weight')) {
            $rows[] = new PreviewGridRow(
                field: 'weight',
                label: self::resolveLabel('weight', $config),
                color: $color,
                cells: $state->getGrid('weight'),
            );

            $rows[] = new PreviewGridRow(
                field: 'oneRepMax',
                label: '1RM',
                color: self::ONE_REP_MAX_COLOR,
                cells: $state->getGrid('oneRepMax'),
            );

            return $rows;
        }

        $mode = $config['mode'] ?? 'manual';

        if ($mode === 'automatic') {
            $cells = self::fillGrid($weeks, $setsPerWeek, '-');
        } else {
            $defaultWeight = (float) ($config['default'] ?? 0);
            $cells = self::fillGrid($weeks, $setsPerWeek, $defaultWeight);
        }

        $rows[] = new PreviewGridRow(
            field: 'weight',
            label: self::resolveLabel('weight', $config),
            color: $color,
            cells: $cells,
        );

        return $rows;
    }

    private static function buildWeekColumn(string $setting, array $config, int $weeks): PreviewGridRow
    {
        $default = $config['default'] ?? '-';
        $cells = array_fill(0, $weeks, $default);

        return new PreviewGridRow(
            field: $setting,
            label: self::resolveLabel($setting, $config),
            color: 'bg-zinc-100 dark:bg-zinc-700/30',
            cells: $cells,
        );
    }

    private static function resolveLabel(string $setting, array $config): string
    {
        $label = ucfirst($setting);
        $enum = ExerciseSetting::tryFrom($setting);

        if ($enum?->settingClass()) {
            $unit = $enum->settingClass()::resolveUnitLabel($config);

            if ($unit !== null) {
                return "{$label} ({$unit})";
            }
        }

        return $label;
    }

    /**
     * @param  array<int, int>  $setsPerWeek
     * @return array<int, array<int, mixed>>
     */
    private static function buildDefaultCells(array $config, int $weeks, array $setsPerWeek): array
    {
        $default = $config['default'] ?? '-';

        return self::fillGrid($weeks, $setsPerWeek, $default);
    }

    /**
     * @param  array<int, int>  $setsPerWeek
     * @return array<int, array<int, mixed>>
     */
    private static function fillGrid(int $weeks, array $setsPerWeek, mixed $value): array
    {
        $grid = [];

        for ($week = 0; $week < $weeks; $week++) {
            $grid[$week] = array_fill(0, $setsPerWeek[$week], $value);
        }

        return $grid;
    }
}
