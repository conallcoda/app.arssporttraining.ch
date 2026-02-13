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

    private const OVERRIDE_ROW_COLORS = [
        'bg-blue-200 dark:bg-blue-700/40',
        'bg-green-200 dark:bg-green-700/40',
        'bg-red-200 dark:bg-red-700/40',
        'bg-purple-200 dark:bg-purple-700/40',
        'bg-cyan-200 dark:bg-cyan-700/40',
        'bg-orange-200 dark:bg-orange-700/40',
        'bg-pink-200 dark:bg-pink-700/40',
        'bg-amber-200 dark:bg-amber-700/40',
    ];

    private const ONE_REP_MAX_COLOR = 'bg-orange-50 dark:bg-orange-900/20';

    private const WEEK_COLUMN_COLOR = 'bg-zinc-100 dark:bg-zinc-700/30';

    private const WEEK_COLUMN_OVERRIDE_COLOR = 'bg-zinc-200 dark:bg-zinc-600/50';

    public static function build(
        array $data,
        ?MeasuredData $measuredData = null,
        int $weeks = self::DEFAULT_WEEKS,
        ?GridOverrides $overrides = null,
        int $sessionsPerWeek = 1,
    ): PreviewGrid {
        $orchestrator = new StrategyOrchestrator($data, $measuredData, $weeks, $overrides);
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
                $weekColumns[] = self::buildWeekColumn($setting, $config, $weeks, $state);

                continue;
            }

            $color = self::ROW_COLORS[$colorIndex] ?? 'bg-zinc-50 dark:bg-zinc-800/20';
            $overrideColor = self::OVERRIDE_ROW_COLORS[$colorIndex] ?? 'bg-zinc-200 dark:bg-zinc-600/50';
            $colorIndex++;

            if ($setting === 'weight') {
                $weightRows = self::buildWeightRows($config, $color, $overrideColor, $weeks, $setsPerWeek, $state);
                foreach ($weightRows as $row) {
                    $rows[] = $row;
                }
            } else {
                $rows[] = self::buildRow($setting, $config, $color, $overrideColor, $weeks, $setsPerWeek, $state);
            }
        }

        return new PreviewGrid(
            rows: $rows,
            weekCount: $weeks,
            setCount: $state->maxSets(),
            setLabel: ($data['sets']['label'] ?? 'Set'),
            weekColumns: $weekColumns,
            summary: $state->getMetadata('weight', 'summary'),
            sessionsPerWeek: $sessionsPerWeek,
        );
    }

    /** @param array<int, int> $setsPerWeek */
    private static function buildRow(string $setting, array $config, string $color, string $overrideColor, int $weeks, array $setsPerWeek, GridState $state): PreviewGridRow
    {
        $defaultGrid = $state->hasGrid($setting)
            ? $state->getGrid($setting)
            : self::buildDefaultCells($config, $weeks, $setsPerWeek);

        $cells = [];
        $overrideMap = [];

        for ($week = 0; $week < $weeks; $week++) {
            $setCount = $setsPerWeek[$week];
            for ($set = 0; $set < $setCount; $set++) {
                $resolved = $state->getResolvedCellValue($setting, $week, $set);
                $cells[$week][$set] = $resolved ?? ($defaultGrid[$week][$set] ?? '-');
                $overrideMap[$week][$set] = $state->isCellOverridden($setting, $week, $set);
            }
        }

        $inputMeta = self::resolveInputMeta($setting, $config);
        $editableMap = self::buildEditableMap($setting, $weeks, $state->maxSets(), $state);

        return new PreviewGridRow(
            field: $setting,
            label: self::resolveLabel($setting, $config),
            color: $color,
            cells: $cells,
            overrideColor: $overrideColor,
            overrides: $overrideMap,
            inputMeta: $inputMeta,
            editableMap: $editableMap,
        );
    }

    /**
     * @param  array<int, int>  $setsPerWeek
     * @return PreviewGridRow[]
     */
    private static function buildWeightRows(array $config, string $color, string $overrideColor, int $weeks, array $setsPerWeek, GridState $state): array
    {
        $rows = [];
        $inputMeta = self::resolveInputMeta('weight', $config);

        if ($state->hasGrid('weight')) {
            $weightCells = [];
            $weightOverrides = [];

            for ($week = 0; $week < $weeks; $week++) {
                $setCount = $setsPerWeek[$week];
                for ($set = 0; $set < $setCount; $set++) {
                    $resolved = $state->getResolvedCellValue('weight', $week, $set);
                    $weightCells[$week][$set] = $resolved ?? ($state->getCellValue('weight', $week, $set) ?? '-');
                    $weightOverrides[$week][$set] = $state->isCellOverridden('weight', $week, $set);
                }
            }

            $maxSets = $state->maxSets();

            $rows[] = new PreviewGridRow(
                field: 'weight',
                label: self::resolveLabel('weight', $config),
                color: $color,
                cells: $weightCells,
                overrideColor: $overrideColor,
                overrides: $weightOverrides,
                inputMeta: $inputMeta,
                editableMap: self::buildEditableMap('weight', $weeks, $maxSets, $state),
            );

            $rows[] = new PreviewGridRow(
                field: 'oneRepMax',
                label: '1RM (kg)',
                color: self::ONE_REP_MAX_COLOR,
                cells: $state->getGrid('oneRepMax'),
                editableMap: self::buildEditableMap('oneRepMax', $weeks, $maxSets, $state),
                lastSessionOnly: true,
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

        $overrideMap = [];
        for ($week = 0; $week < $weeks; $week++) {
            $setCount = $setsPerWeek[$week];
            for ($set = 0; $set < $setCount; $set++) {
                $resolved = $state->getResolvedCellValue('weight', $week, $set);
                if ($resolved !== null) {
                    $cells[$week][$set] = $resolved;
                }
                $overrideMap[$week][$set] = $state->isCellOverridden('weight', $week, $set);
            }
        }

        $rows[] = new PreviewGridRow(
            field: 'weight',
            label: self::resolveLabel('weight', $config),
            color: $color,
            cells: $cells,
            overrideColor: $overrideColor,
            overrides: $overrideMap,
            inputMeta: $inputMeta,
            editableMap: self::buildEditableMap('weight', $weeks, $state->maxSets(), $state),
        );

        return $rows;
    }

    private static function buildWeekColumn(string $setting, array $config, int $weeks, GridState $state): PreviewGridRow
    {
        $default = $config['default'] ?? '-';
        $cells = [];
        $overrideMap = [];

        for ($week = 0; $week < $weeks; $week++) {
            $cells[$week] = $state->getResolvedWeekValue($setting, $week, $default);
            $overrideMap[$week] = $state->isWeekOverridden($setting, $week);
        }

        $inputMeta = self::resolveInputMeta($setting, $config);

        return new PreviewGridRow(
            field: $setting,
            label: self::resolveLabel($setting, $config),
            color: self::WEEK_COLUMN_COLOR,
            cells: $cells,
            overrideColor: self::WEEK_COLUMN_OVERRIDE_COLOR,
            overrides: $overrideMap,
            inputMeta: $inputMeta,
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

    private static function resolveInputMeta(string $setting, array $config): ?CellInputMeta
    {
        $enum = ExerciseSetting::tryFrom($setting);

        if ($enum?->settingClass()) {
            return $enum->settingClass()::inputMeta($config);
        }

        return new CellInputMeta;
    }

    /** @return array<int, array<int, bool>> */
    private static function buildEditableMap(string $field, int $weeks, int $maxSets, GridState $state): array
    {
        $editableMap = [];

        for ($week = 0; $week < $weeks; $week++) {
            for ($set = 0; $set < $maxSets; $set++) {
                if (! $state->isCellEditable($field, $week, $set)) {
                    $editableMap[$week][$set] = false;
                }
            }
        }

        return $editableMap;
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
