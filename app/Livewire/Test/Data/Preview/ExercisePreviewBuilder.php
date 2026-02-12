<?php

namespace App\Livewire\Test\Data\Preview;

use App\Livewire\Test\Data\Settings\RepsSetting;
use App\Livewire\Test\Data\Settings\SetsSetting;
use App\Livewire\Test\Data\Settings\WeightSetting;
use App\Livewire\Test\Data\Strategies\Reps\PairedRepStrategy;
use App\Livewire\Test\Data\Strategies\Sets\DeloadSetsStrategy;
use App\Livewire\Test\Data\Strategies\Weight\MeasuredData;
use App\Livewire\Test\Data\Strategies\Weight\OneRepMaxFixedStrategy;

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
        $setsSetting = SetsSetting::from($data['sets'] ?? []);
        $deload = new DeloadSetsStrategy($setsSetting);
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
        $weekColumns = [];
        $colorIndex = 0;
        $summary = null;

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
                $weightRows = self::buildWeightRows($config, $color, $weeks, $setsPerWeek, $measuredData);
                foreach ($weightRows['rows'] as $row) {
                    $rows[] = $row;
                }
                if ($weightRows['summary'] !== null) {
                    $summary = $weightRows['summary'];
                }
            } else {
                $row = self::buildRow($setting, $config, $color, $weeks, $setsPerWeek);
                if ($row) {
                    $rows[] = $row;
                }
            }
        }

        return new PreviewGrid(
            rows: $rows,
            weekCount: $weeks,
            setCount: $maxSets,
            setLabel: $setsSetting->label,
            weekColumns: $weekColumns,
            summary: $summary,
        );
    }

    /** @param array<int, int> $setsPerWeek */
    private static function buildRow(string $setting, array $config, string $color, int $weeks, array $setsPerWeek): ?PreviewGridRow
    {
        $mode = $config['mode'] ?? 'manual';

        $cells = match ($setting) {
            'reps' => self::buildRepsCells($config, $mode, $weeks, $setsPerWeek),
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
     * @return array{rows: PreviewGridRow[], summary: array|null}
     */
    private static function buildWeightRows(array $config, string $color, int $weeks, array $setsPerWeek, ?MeasuredData $measuredData): array
    {
        $mode = $config['mode'] ?? 'manual';
        $rows = [];
        $summary = null;

        if ($mode === 'automatic' && $measuredData !== null) {
            $weightSetting = WeightSetting::from($config);
            $strategy = new OneRepMaxFixedStrategy($weightSetting, $measuredData);
            $result = $strategy->generate($weeks, $setsPerWeek);

            if ($result !== null) {
                $rows[] = new PreviewGridRow(
                    field: 'weight',
                    label: 'Weight',
                    color: $color,
                    cells: $result['weights'],
                );

                $rows[] = new PreviewGridRow(
                    field: 'oneRepMax',
                    label: '1RM',
                    color: self::ONE_REP_MAX_COLOR,
                    cells: $result['oneRepMax'],
                );

                $summary = $strategy->getSummary();

                return ['rows' => $rows, 'summary' => $summary];
            }
        }

        if ($mode === 'automatic') {
            $cells = self::fillGrid($weeks, $setsPerWeek, '-');
        } else {
            $defaultWeight = (float) ($config['default'] ?? 0);
            $cells = self::fillGrid($weeks, $setsPerWeek, $defaultWeight);
        }

        $rows[] = new PreviewGridRow(
            field: 'weight',
            label: 'Weight',
            color: $color,
            cells: $cells,
        );

        return ['rows' => $rows, 'summary' => null];
    }

    /**
     * @param  array<int, int>  $setsPerWeek
     * @return array<int, array<int, int>>
     */
    private static function buildRepsCells(array $config, string $mode, int $weeks, array $setsPerWeek): array
    {
        if ($mode === 'automatic') {
            $repsSetting = RepsSetting::from($config);

            return (new PairedRepStrategy($repsSetting))->generate($weeks, $setsPerWeek);
        }

        $defaultReps = (int) ($config['default'] ?? 10);

        return self::fillGrid($weeks, $setsPerWeek, $defaultReps);
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

    private static function buildWeekColumn(string $setting, array $config, int $weeks): PreviewGridRow
    {
        $default = $config['default'] ?? '-';
        $cells = array_fill(0, $weeks, $default);

        return new PreviewGridRow(
            field: $setting,
            label: ucfirst($setting),
            color: 'bg-zinc-100 dark:bg-zinc-700/30',
            cells: $cells,
        );
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
