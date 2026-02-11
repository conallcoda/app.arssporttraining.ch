<?php

namespace App\Livewire\Test\Data\Preview;

use App\Livewire\Test\Data\Strategies\Reps\PairedRepStrategy;
use App\Livewire\Test\Data\Strategies\Sets\DeloadSetsStrategy;

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

    public static function build(array $data, int $weeks = self::DEFAULT_WEEKS): PreviewGrid
    {
        $setsConfig = $data['sets'] ?? [];
        $deload = DeloadSetsStrategy::fromConfig($setsConfig);
        $setsPerWeek = $deload->generate($weeks);
        $maxSets = max($setsPerWeek);

        $settings = $data['settings'] ?? [];

        usort($settings, function (string $a, string $b) {
            $priorityA = array_search($a, self::PRIORITY);
            $priorityB = array_search($b, self::PRIORITY);

            return ($priorityA === false ? PHP_INT_MAX : $priorityA)
                <=> ($priorityB === false ? PHP_INT_MAX : $priorityB);
        });

        $rows = [];

        foreach ($settings as $index => $setting) {
            $config = $data[$setting] ?? [];
            $color = self::ROW_COLORS[$index] ?? 'bg-zinc-50 dark:bg-zinc-800/20';
            $row = self::buildRow($setting, $config, $color, $weeks, $setsPerWeek);

            if ($row) {
                $rows[] = $row;
            }
        }

        $setLabel = $setsConfig['label'] ?? 'Set';

        return new PreviewGrid(
            rows: $rows,
            weekCount: $weeks,
            setCount: $maxSets,
            setLabel: $setLabel,
        );
    }

    /** @param array<int, int> $setsPerWeek */
    private static function buildRow(string $setting, array $config, string $color, int $weeks, array $setsPerWeek): ?PreviewGridRow
    {
        $mode = $config['mode'] ?? 'manual';

        $cells = match ($setting) {
            'reps' => self::buildRepsCells($config, $mode, $weeks, $setsPerWeek),
            'weight' => self::buildWeightCells($config, $mode, $weeks, $setsPerWeek),
            default => self::buildDefaultCells($config, $weeks, $setsPerWeek),
        };

        return new PreviewGridRow(
            field: $setting,
            label: ucfirst($setting),
            color: $color,
            cells: $cells,
        );
    }

    /**
     * @param  array<int, int>  $setsPerWeek
     * @return array<int, array<int, int>>
     */
    private static function buildRepsCells(array $config, string $mode, int $weeks, array $setsPerWeek): array
    {
        if ($mode === 'automatic') {
            return PairedRepStrategy::fromConfig($config)->generate($weeks, $setsPerWeek);
        }

        $defaultReps = (int) ($config['default'] ?? 10);

        return self::fillGrid($weeks, $setsPerWeek, $defaultReps);
    }

    /**
     * @param  array<int, int>  $setsPerWeek
     * @return array<int, array<int, string|float>>
     */
    private static function buildWeightCells(array $config, string $mode, int $weeks, array $setsPerWeek): array
    {
        if ($mode === 'automatic') {
            return self::fillGrid($weeks, $setsPerWeek, '-');
        }

        $defaultWeight = (float) ($config['default'] ?? 0);

        return self::fillGrid($weeks, $setsPerWeek, $defaultWeight);
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
