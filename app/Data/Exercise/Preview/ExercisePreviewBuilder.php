<?php

namespace App\Data\Exercise\Preview;

use App\Data\Exercise\ExerciseSetting;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use Coda\Cms\Support\ColorPalette;
use Illuminate\Support\Str;

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
        'heartRate',
        'heartRateZone',
        'tempo',
        'rest',
    ];

    private const WEEK_COLUMN_COLOR = 'bg-zinc-100 dark:bg-zinc-700/30';

    private const WEEK_COLUMN_OVERRIDE_COLOR = 'bg-zinc-200 dark:bg-zinc-600/50';

    public static function build(
        array $data,
        ?WeightProgressionSetting $measuredData = null,
        int $weeks = self::DEFAULT_WEEKS,
        ?GridOverrides $overrides = null,
        int $sessionsPerWeek = 1,
        ?GridOverrides $highlightOverrides = null,
        ?int $maxHR = null,
        ?int $iatPercent = null,
        ?string $startsAtDate = null,
        array $weekSessionDates = [],
        array $lockedSessionsByWeek = [],
    ): PreviewGrid {
        $orchestrator = new StrategyOrchestrator($data, $measuredData, $weeks, $overrides, $maxHR, $iatPercent);
        $state = $orchestrator->execute();

        if ($highlightOverrides !== null) {
            $state->setHighlightOverrides($highlightOverrides);
        }

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
        $applicableWeeks = self::buildApplicableWeekMap($weeks, $startsAtDate, $weekSessionDates);
        $lockedWeekMap = self::buildLockedWeekMap($weeks, $lockedSessionsByWeek);

        foreach ($settings as $setting) {
            $config = $data[$setting] ?? [];
            $applyPer = $config['applyPer'] ?? 'session';

            if ($applyPer === 'week') {
                $weekColumns[] = self::buildWeekColumn($setting, $config, $weeks, $state, $applicableWeeks, $lockedWeekMap);

                continue;
            }

            $rowColorName = ColorPalette::ROW_COLORS[$colorIndex] ?? null;
            $color = $rowColorName ? ColorPalette::light($rowColorName) : 'bg-zinc-50 dark:bg-zinc-800/20';
            $overrideColor = $rowColorName ? ColorPalette::lightStrong($rowColorName) : 'bg-zinc-200 dark:bg-zinc-600/50';
            $colorIndex++;

            if ($setting === 'weight') {
                $weightRows = self::buildWeightRows($config, $color, $overrideColor, $weeks, $setsPerWeek, $state, $applicableWeeks, $lockedWeekMap);
                foreach ($weightRows as $row) {
                    $rows[] = $row;
                }
            } else {
                $rows[] = self::buildRow($setting, $config, $color, $overrideColor, $weeks, $setsPerWeek, $state, $applicableWeeks, $lockedWeekMap);
            }
        }

        $setsCells = [];
        $setsOverrideMap = [];

        for ($week = 0; $week < $weeks; $week++) {
            if ($applicableWeeks[$week]) {
                $setsCells[$week] = $setsPerWeek[$week];
                $setsOverrideMap[$week] = $state->isWeekOverridden('sets', $week);

                continue;
            }

            $showsHistoricalOverride = $lockedWeekMap[$week] && $state->isWeekOverridden('sets', $week);
            $setsCells[$week] = $showsHistoricalOverride
                ? $state->getResolvedWeekValue('sets', $week, $setsPerWeek[$week] ?? '-')
                : '-';
            $setsOverrideMap[$week] = $showsHistoricalOverride;
        }

        $weekColumns[] = new PreviewGridRow(
            field: 'sets',
            label: Str::plural($data['sets']['label'] ?? 'Set'),
            color: self::WEEK_COLUMN_COLOR,
            cells: $setsCells,
            overrideColor: self::WEEK_COLUMN_OVERRIDE_COLOR,
            overrides: $setsOverrideMap,
            inputMeta: new CellInputMeta(inputType: 'number', inputStep: '1', min: 0),
            editableMap: array_map(fn (bool $isApplicable) => $isApplicable, $applicableWeeks),
        );

        $summary = $state->getMetadata('weight', 'summary');

        if ($summary !== null) {
            $summary['modifier'] = $data['weight']['oneRepMaxModifier'] ?? 100;
        }

        return new PreviewGrid(
            rows: $rows,
            weekCount: $weeks,
            setCount: $state->maxSets(),
            setLabel: ($data['sets']['label'] ?? 'Set'),
            weekColumns: $weekColumns,
            summary: $summary,
            sessionsPerWeek: $sessionsPerWeek,
        );
    }

    /** @param array<int, int> $setsPerWeek */
    private static function buildRow(string $setting, array $config, string $color, string $overrideColor, int $weeks, array $setsPerWeek, GridState $state, array $applicableWeeks, array $lockedWeekMap): PreviewGridRow
    {
        $defaultGrid = $state->hasGrid($setting)
            ? $state->getGrid($setting)
            : self::buildDefaultCells($config, $weeks, $setsPerWeek);

        $cells = [];
        $overrideMap = [];

        for ($week = 0; $week < $weeks; $week++) {
            $setCount = $setsPerWeek[$week];
            for ($set = 0; $set < $setCount; $set++) {
                if (! ($applicableWeeks[$week] ?? true)) {
                    $showsHistoricalOverride = ($lockedWeekMap[$week] ?? false) && $state->isCellOverridden($setting, $week, $set);
                    $cells[$week][$set] = $showsHistoricalOverride
                        ? ($state->getResolvedCellValue($setting, $week, $set) ?? '-')
                        : '-';
                    $overrideMap[$week][$set] = $showsHistoricalOverride;

                    continue;
                }

                $resolved = $state->getResolvedCellValue($setting, $week, $set);
                $cells[$week][$set] = $resolved ?? ($defaultGrid[$week][$set] ?? '-');
                $overrideMap[$week][$set] = $state->isCellOverridden($setting, $week, $set);
            }
        }

        $inputMeta = self::resolveInputMeta($setting, $config);
        $editableMap = self::buildEditableMap($setting, $weeks, $state->maxSets(), $state, $applicableWeeks);
        [$cellColorMap, $cellOverrideColorMap] = self::buildCellColorMaps($setting, $cells, $state);

        return new PreviewGridRow(
            field: $setting,
            label: self::resolveLabel($setting, $config),
            color: $color,
            cells: $cells,
            overrideColor: $overrideColor,
            overrides: $overrideMap,
            inputMeta: $inputMeta,
            editableMap: $editableMap,
            cellColorMap: $cellColorMap,
            cellOverrideColorMap: $cellOverrideColorMap,
        );
    }

    /**
     * @param  array<int, int>  $setsPerWeek
     * @return PreviewGridRow[]
     */
    private static function buildWeightRows(array $config, string $color, string $overrideColor, int $weeks, array $setsPerWeek, GridState $state, array $applicableWeeks, array $lockedWeekMap): array
    {
        $rows = [];
        $inputMeta = self::resolveInputMeta('weight', $config);

        if ($state->hasGrid('weight')) {
            $weightCells = [];
            $weightOverrides = [];

            for ($week = 0; $week < $weeks; $week++) {
                $setCount = $setsPerWeek[$week];
                for ($set = 0; $set < $setCount; $set++) {
                    if (! ($applicableWeeks[$week] ?? true)) {
                        $showsHistoricalOverride = ($lockedWeekMap[$week] ?? false) && $state->isCellOverridden('weight', $week, $set);
                        $weightCells[$week][$set] = $showsHistoricalOverride
                            ? ($state->getResolvedCellValue('weight', $week, $set) ?? '-')
                            : '-';
                        $weightOverrides[$week][$set] = $showsHistoricalOverride;

                        continue;
                    }

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
                editableMap: self::buildEditableMap('weight', $weeks, $maxSets, $state, $applicableWeeks),
            );

            $oneRepMaxCells = $state->getGrid('oneRepMax');
            foreach (array_keys($applicableWeeks) as $week) {
                if (! ($applicableWeeks[$week] ?? true) && ! ($lockedWeekMap[$week] ?? false)) {
                    foreach ($oneRepMaxCells[$week] ?? [] as $set => $_value) {
                        $oneRepMaxCells[$week][$set] = '-';
                    }
                }
            }

            $rows[] = new PreviewGridRow(
                field: 'oneRepMax',
                label: '1RM (kg)',
                color: ColorPalette::light('orange'),
                cells: $oneRepMaxCells,
                editableMap: self::buildEditableMap('oneRepMax', $weeks, $maxSets, $state, $applicableWeeks),
                lastSessionOnly: true,
            );

            return $rows;
        }

        $mode = $config['mode'] ?? 'manual';

        if ($mode === 'automatic') {
            $cells = self::fillGrid($weeks, $setsPerWeek, '-');
        } else {
            $rawDefault = $config['default'] ?? null;
            $defaultWeight = ($rawDefault === null || $rawDefault === '') ? '-' : (float) $rawDefault;
            $cells = self::fillGrid($weeks, $setsPerWeek, $defaultWeight);
        }

        $overrideMap = [];
        for ($week = 0; $week < $weeks; $week++) {
            $setCount = $setsPerWeek[$week];
            for ($set = 0; $set < $setCount; $set++) {
                if (! ($applicableWeeks[$week] ?? true)) {
                    $showsHistoricalOverride = ($lockedWeekMap[$week] ?? false) && $state->isCellOverridden('weight', $week, $set);
                    $cells[$week][$set] = $showsHistoricalOverride
                        ? ($state->getResolvedCellValue('weight', $week, $set) ?? '-')
                        : '-';
                    $overrideMap[$week][$set] = $showsHistoricalOverride;

                    continue;
                }

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
            editableMap: self::buildEditableMap('weight', $weeks, $state->maxSets(), $state, $applicableWeeks),
        );

        return $rows;
    }

    private static function buildWeekColumn(string $setting, array $config, int $weeks, GridState $state, array $applicableWeeks, array $lockedWeekMap): PreviewGridRow
    {
        $rawDefault = $config['default'] ?? null;
        $default = ($rawDefault === null || $rawDefault === '') ? '-' : $rawDefault;
        $cells = [];
        $overrideMap = [];

        for ($week = 0; $week < $weeks; $week++) {
            if (! ($applicableWeeks[$week] ?? true)) {
                $showsHistoricalOverride = ($lockedWeekMap[$week] ?? false) && $state->isWeekOverridden($setting, $week);
                $cells[$week] = $showsHistoricalOverride
                    ? $state->getResolvedWeekValue($setting, $week, $default)
                    : '-';
                $overrideMap[$week] = $showsHistoricalOverride;

                continue;
            }

            $cells[$week] = $state->getResolvedWeekValue($setting, $week, $default);
            $overrideMap[$week] = $state->isWeekOverridden($setting, $week);
        }

        $inputMeta = self::resolveInputMeta($setting, $config);

        $cellColorMap = [];
        $cellOverrideColorMap = [];

        foreach ($cells as $week => $value) {
            $color = $state->getCellColorByValue($setting, $value);

            if ($color !== null) {
                $cellColorMap[$week] = $color;
            }

            $overrideColor = $state->getCellOverrideColorByValue($setting, $value);

            if ($overrideColor !== null) {
                $cellOverrideColorMap[$week] = $overrideColor;
            }
        }

        return new PreviewGridRow(
            field: $setting,
            label: self::resolveLabel($setting, $config),
            color: self::WEEK_COLUMN_COLOR,
            cells: $cells,
            overrideColor: self::WEEK_COLUMN_OVERRIDE_COLOR,
            overrides: $overrideMap,
            inputMeta: $inputMeta,
            editableMap: array_map(fn (bool $isApplicable) => $isApplicable, $applicableWeeks),
            cellColorMap: $cellColorMap,
            cellOverrideColorMap: $cellOverrideColorMap,
        );
    }

    private static function resolveLabel(string $setting, array $config): string
    {
        if (! empty($config['label'])) {
            return $config['label'];
        }

        $enum = ExerciseSetting::tryFrom($setting);
        $label = $enum?->label() ?? ucfirst($setting);

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
    private static function buildEditableMap(string $field, int $weeks, int $maxSets, GridState $state, array $applicableWeeks = []): array
    {
        $editableMap = [];

        for ($week = 0; $week < $weeks; $week++) {
            if (! ($applicableWeeks[$week] ?? true)) {
                for ($set = 0; $set < $maxSets; $set++) {
                    $editableMap[$week][$set] = false;
                }

                continue;
            }

            for ($set = 0; $set < $maxSets; $set++) {
                if (! $state->isCellEditable($field, $week, $set)) {
                    $editableMap[$week][$set] = false;
                }
            }
        }

        return $editableMap;
    }

    /** @return array<int, bool> */
    private static function buildApplicableWeekMap(int $weeks, ?string $startsAtDate, array $weekSessionDates): array
    {
        $applicableWeeks = [];

        for ($week = 0; $week < $weeks; $week++) {
            if ($startsAtDate === null || $startsAtDate === '') {
                $applicableWeeks[$week] = true;

                continue;
            }

            $sessionDates = $weekSessionDates[$week] ?? [];

            if ($sessionDates === []) {
                $applicableWeeks[$week] = true;

                continue;
            }

            $applicableWeeks[$week] = collect($sessionDates)
                ->contains(fn (mixed $sessionDate): bool => is_string($sessionDate) && $sessionDate >= $startsAtDate);
        }

        return $applicableWeeks;
    }

    /** @return array<int, bool> */
    private static function buildLockedWeekMap(int $weeks, array $lockedSessionsByWeek): array
    {
        $lockedWeekMap = [];

        for ($week = 0; $week < $weeks; $week++) {
            $lockedWeekMap[$week] = collect($lockedSessionsByWeek[$week] ?? [])
                ->contains(fn (mixed $locked): bool => $locked === true);
        }

        return $lockedWeekMap;
    }

    /**
     * @return array{array<int, array<int, string>>, array<int, array<int, string>>}
     */
    private static function buildCellColorMaps(string $setting, array $cells, GridState $state): array
    {
        $colorMap = [];
        $overrideColorMap = [];

        foreach ($cells as $week => $weekCells) {
            foreach ($weekCells as $set => $value) {
                $color = $state->getCellColor($setting, $week, $set)
                    ?? $state->getCellColorByValue($setting, $value);

                if ($color !== null) {
                    $colorMap[$week][$set] = $color;
                }

                $overrideColor = $state->getCellOverrideColor($setting, $week, $set)
                    ?? $state->getCellOverrideColorByValue($setting, $value);

                if ($overrideColor !== null) {
                    $overrideColorMap[$week][$set] = $overrideColor;
                }
            }
        }

        return [$colorMap, $overrideColorMap];
    }

    /**
     * @param  array<int, int>  $setsPerWeek
     * @return array<int, array<int, mixed>>
     */
    private static function buildDefaultCells(array $config, int $weeks, array $setsPerWeek): array
    {
        $rawDefault = $config['default'] ?? null;
        $default = ($rawDefault === null || $rawDefault === '') ? '-' : $rawDefault;

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
