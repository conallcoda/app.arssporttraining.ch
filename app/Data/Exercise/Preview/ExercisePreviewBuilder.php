<?php

namespace App\Data\Exercise\Preview;

use App\Data\Exercise\ExerciseSetting;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Exercise\Strategies\HeartRate\HeartRateZoneCellColors;
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
        ?GridOverrides $historicalOverrides = null,
        array $explicitWeekSessionCounts = [],
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
        $applicableWeeks = self::buildApplicableWeekMap($weeks, $startsAtDate, $weekSessionDates, $lockedSessionsByWeek);
        $sessionCounts = self::buildSessionCountMap($weeks, $sessionsPerWeek, $weekSessionDates, $explicitWeekSessionCounts);
        $applicableSessions = self::buildApplicableSessionMap($weeks, $sessionsPerWeek, $startsAtDate, $weekSessionDates, $explicitWeekSessionCounts, $lockedSessionsByWeek);
        $lockedWeekMap = self::buildLockedWeekMap($weeks, $lockedSessionsByWeek);

        foreach ($settings as $setting) {
            $config = $data[$setting] ?? [];
            $applyPer = $config['applyPer'] ?? 'session';

            if ($applyPer === 'week') {
                $weekColumns[] = self::buildWeekColumn($setting, $config, $weeks, $state, $applicableWeeks, $applicableSessions, $sessionCounts, $lockedWeekMap, $lockedSessionsByWeek, $historicalOverrides);

                continue;
            }

            $rowColorName = ColorPalette::ROW_COLORS[$colorIndex] ?? null;
            $color = $rowColorName ? ColorPalette::light($rowColorName) : 'bg-zinc-50 dark:bg-zinc-800/20';
            $overrideColor = $rowColorName ? ColorPalette::lightStrong($rowColorName) : 'bg-zinc-200 dark:bg-zinc-600/50';
            $colorIndex++;

            if ($setting === 'weight') {
                $weightRows = self::buildWeightRows($config, $color, $overrideColor, $weeks, $setsPerWeek, $state, $applicableWeeks, $applicableSessions, $sessionCounts, $lockedWeekMap, $lockedSessionsByWeek, $historicalOverrides);
                foreach ($weightRows as $row) {
                    $rows[] = $row;
                }
            } else {
                $rows[] = self::buildRow($setting, $config, $color, $overrideColor, $weeks, $setsPerWeek, $state, $applicableWeeks, $applicableSessions, $sessionCounts, $lockedWeekMap, $lockedSessionsByWeek, $historicalOverrides);
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

            $showsHistoricalOverride = $lockedWeekMap[$week] && self::hasHistoricalWeekOverride($historicalOverrides, 'sets', $week);
            $setsCells[$week] = $showsHistoricalOverride
                ? self::getHistoricalWeekValue($historicalOverrides, 'sets', $week, $setsPerWeek[$week] ?? '-')
                : '-';
            $setsOverrideMap[$week] = $showsHistoricalOverride;
        }

        $weekColumns[] = new PreviewGridRow(
            field: 'sets',
            label: Str::plural($data['sets']['label'] ?? 'Set'),
            color: self::WEEK_COLUMN_COLOR,
            clickField: 'sets',
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
            weekSessionCounts: $sessionCounts,
        );
    }

    /** @param array<int, int> $setsPerWeek */
    private static function buildRow(string $setting, array $config, string $color, string $overrideColor, int $weeks, array $setsPerWeek, GridState $state, array $applicableWeeks, array $applicableSessions, array $sessionCounts, array $lockedWeekMap, array $lockedSessionsByWeek = [], ?GridOverrides $historicalOverrides = null): PreviewGridRow
    {
        $defaultGrid = $state->hasGrid($setting)
            ? $state->getGrid($setting)
            : self::buildDefaultCells($config, $weeks, $setsPerWeek);

        $cells = [];
        $overrideMap = [];
        $sessionCells = [];
        $sessionOverrideMap = [];

        for ($week = 0; $week < $weeks; $week++) {
            $setCount = $setsPerWeek[$week];
            for ($set = 0; $set < $setCount; $set++) {
                if (! ($applicableWeeks[$week] ?? true)) {
                    $showsHistoricalOverride = ($lockedWeekMap[$week] ?? false) && self::hasHistoricalCellOverride($historicalOverrides, $setting, $week, $set);
                    $fallbackValue = $state->getResolvedCellValue($setting, $week, $set)
                        ?? ($defaultGrid[$week][$set] ?? '-');
                    $cells[$week][$set] = $showsHistoricalOverride
                        ? (self::getHistoricalCellValue($historicalOverrides, $setting, $week, $set) ?? $fallbackValue)
                        : (($lockedWeekMap[$week] ?? false) ? $fallbackValue : '-');
                    $overrideMap[$week][$set] = $showsHistoricalOverride;

                    continue;
                }

                $resolved = $state->getResolvedCellValue($setting, $week, $set);
                $cells[$week][$set] = $resolved ?? ($defaultGrid[$week][$set] ?? '-');
                $overrideMap[$week][$set] = $state->isCellOverridden($setting, $week, $set);

                for ($session = 0; $session < ($sessionCounts[$week] ?? 1); $session++) {
                    if (! ($applicableSessions[$week][$session] ?? true)) {
                        $sessionShowsHistoricalOverride = ($lockedWeekMap[$week] ?? false) && self::hasHistoricalCellOverride($historicalOverrides, $setting, $week, $set, $session);
                        $sessionFallbackValue = $state->getResolvedCellValue($setting, $week, $set, $session)
                            ?? ($defaultGrid[$week][$set] ?? '-');
                        $sessionCells[$week][$session][$set] = $sessionShowsHistoricalOverride
                            ? (self::getHistoricalCellValue($historicalOverrides, $setting, $week, $set, $session) ?? $sessionFallbackValue)
                            : ((($lockedWeekMap[$week] ?? false) && ($lockedSessionsByWeek[$week][$session] ?? false)) ? $sessionFallbackValue : '-');
                        $sessionOverrideMap[$week][$session][$set] = $sessionShowsHistoricalOverride;

                        continue;
                    }

                    $sessionResolved = $state->getResolvedCellValue($setting, $week, $set, $session);
                    $sessionCells[$week][$session][$set] = $sessionResolved ?? ($defaultGrid[$week][$set] ?? '-');
                    $sessionOverrideMap[$week][$session][$set] = $state->isCellOverridden($setting, $week, $set, $session);
                }
            }
        }

        $inputMeta = self::resolveInputMeta($setting, $config);
        $editableMap = self::buildEditableMap($setting, $weeks, $state->maxSets(), $state, $applicableWeeks);
        [$cellColorMap, $cellOverrideColorMap, $sessionCellColorMap, $sessionCellOverrideColorMap] = self::buildCellColorMaps($setting, $cells, $sessionCells, $state);

        return new PreviewGridRow(
            field: $setting,
            label: self::resolveLabel($setting, $config),
            color: $color,
            clickField: Str::snake($setting),
            cells: $cells,
            sessionCells: $sessionCells,
            overrideColor: $overrideColor,
            overrides: $overrideMap,
            sessionOverrides: $sessionOverrideMap,
            inputMeta: $inputMeta,
            editableMap: $editableMap,
            cellColorMap: $cellColorMap,
            cellOverrideColorMap: $cellOverrideColorMap,
            sessionCellColorMap: $sessionCellColorMap,
            sessionCellOverrideColorMap: $sessionCellOverrideColorMap,
        );
    }

    /**
     * @param  array<int, int>  $setsPerWeek
     * @return PreviewGridRow[]
     */
    private static function buildWeightRows(array $config, string $color, string $overrideColor, int $weeks, array $setsPerWeek, GridState $state, array $applicableWeeks, array $applicableSessions, array $sessionCounts, array $lockedWeekMap, array $lockedSessionsByWeek = [], ?GridOverrides $historicalOverrides = null): array
    {
        $rows = [];
        $inputMeta = self::resolveInputMeta('weight', $config);

        if ($state->hasGrid('weight')) {
            $weightCells = [];
            $weightOverrides = [];
            $weightSessionCells = [];
            $weightSessionOverrides = [];

            for ($week = 0; $week < $weeks; $week++) {
                $setCount = $setsPerWeek[$week];
                for ($set = 0; $set < $setCount; $set++) {
                    if (! ($applicableWeeks[$week] ?? true)) {
                        $showsHistoricalOverride = ($lockedWeekMap[$week] ?? false) && self::hasHistoricalCellOverride($historicalOverrides, 'weight', $week, $set);
                        $fallbackValue = $state->getResolvedCellValue('weight', $week, $set) ?? ($state->getCellValue('weight', $week, $set) ?? '-');
                        $weightCells[$week][$set] = $showsHistoricalOverride
                            ? (self::getHistoricalCellValue($historicalOverrides, 'weight', $week, $set) ?? $fallbackValue)
                            : (($lockedWeekMap[$week] ?? false) ? $fallbackValue : '-');
                        $weightOverrides[$week][$set] = $showsHistoricalOverride;

                        continue;
                    }

                    $resolved = $state->getResolvedCellValue('weight', $week, $set);
                    $weightCells[$week][$set] = $resolved ?? ($state->getCellValue('weight', $week, $set) ?? '-');
                    $weightOverrides[$week][$set] = $state->isCellOverridden('weight', $week, $set);

                    for ($session = 0; $session < ($sessionCounts[$week] ?? 1); $session++) {
                        if (! ($applicableSessions[$week][$session] ?? true)) {
                            $sessionShowsHistoricalOverride = ($lockedWeekMap[$week] ?? false) && self::hasHistoricalCellOverride($historicalOverrides, 'weight', $week, $set, $session);
                            $sessionFallbackValue = $state->getResolvedCellValue('weight', $week, $set, $session)
                                ?? ($state->getCellValue('weight', $week, $set) ?? '-');
                            $weightSessionCells[$week][$session][$set] = $sessionShowsHistoricalOverride
                                ? (self::getHistoricalCellValue($historicalOverrides, 'weight', $week, $set, $session) ?? $sessionFallbackValue)
                                : ((($lockedWeekMap[$week] ?? false) && ($lockedSessionsByWeek[$week][$session] ?? false)) ? $sessionFallbackValue : '-');
                            $weightSessionOverrides[$week][$session][$set] = $sessionShowsHistoricalOverride;

                            continue;
                        }

                        $sessionResolved = $state->getResolvedCellValue('weight', $week, $set, $session);
                        $weightSessionCells[$week][$session][$set] = $sessionResolved ?? ($state->getCellValue('weight', $week, $set) ?? '-');
                        $weightSessionOverrides[$week][$session][$set] = $state->isCellOverridden('weight', $week, $set, $session);
                    }
                }
            }

            $maxSets = $state->maxSets();

            $rows[] = new PreviewGridRow(
                field: 'weight',
                label: self::resolveLabel('weight', $config),
                color: $color,
                clickField: 'weight',
                cells: $weightCells,
                sessionCells: $weightSessionCells,
                overrideColor: $overrideColor,
                overrides: $weightOverrides,
                sessionOverrides: $weightSessionOverrides,
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
                clickField: 'weight',
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
        $sessionCells = [];
        $sessionOverrideMap = [];
        for ($week = 0; $week < $weeks; $week++) {
            $setCount = $setsPerWeek[$week];
            for ($set = 0; $set < $setCount; $set++) {
                if (! ($applicableWeeks[$week] ?? true)) {
                    $showsHistoricalOverride = ($lockedWeekMap[$week] ?? false) && self::hasHistoricalCellOverride($historicalOverrides, 'weight', $week, $set);
                    $fallbackValue = $state->getResolvedCellValue('weight', $week, $set) ?? $cells[$week][$set];
                    $cells[$week][$set] = $showsHistoricalOverride
                        ? (self::getHistoricalCellValue($historicalOverrides, 'weight', $week, $set) ?? $fallbackValue)
                        : (($lockedWeekMap[$week] ?? false) ? $fallbackValue : '-');
                    $overrideMap[$week][$set] = $showsHistoricalOverride;

                    continue;
                }

                $resolved = $state->getResolvedCellValue('weight', $week, $set);
                if ($resolved !== null) {
                    $cells[$week][$set] = $resolved;
                }
                $overrideMap[$week][$set] = $state->isCellOverridden('weight', $week, $set);

                for ($session = 0; $session < ($sessionCounts[$week] ?? 1); $session++) {
                    if (! ($applicableSessions[$week][$session] ?? true)) {
                        $sessionShowsHistoricalOverride = ($lockedWeekMap[$week] ?? false) && self::hasHistoricalCellOverride($historicalOverrides, 'weight', $week, $set, $session);
                        $sessionFallbackValue = $state->getResolvedCellValue('weight', $week, $set, $session) ?? $cells[$week][$set];
                        $sessionCells[$week][$session][$set] = $sessionShowsHistoricalOverride
                            ? (self::getHistoricalCellValue($historicalOverrides, 'weight', $week, $set, $session) ?? $sessionFallbackValue)
                            : ((($lockedWeekMap[$week] ?? false) && ($lockedSessionsByWeek[$week][$session] ?? false)) ? $sessionFallbackValue : '-');
                        $sessionOverrideMap[$week][$session][$set] = $sessionShowsHistoricalOverride;

                        continue;
                    }

                    $sessionResolved = $state->getResolvedCellValue('weight', $week, $set, $session);
                    $sessionCells[$week][$session][$set] = $sessionResolved ?? $cells[$week][$set];
                    $sessionOverrideMap[$week][$session][$set] = $state->isCellOverridden('weight', $week, $set, $session);
                }
            }
        }

        $rows[] = new PreviewGridRow(
            field: 'weight',
            label: self::resolveLabel('weight', $config),
            color: $color,
            clickField: 'weight',
            cells: $cells,
            sessionCells: $sessionCells,
            overrideColor: $overrideColor,
            overrides: $overrideMap,
            sessionOverrides: $sessionOverrideMap,
            inputMeta: $inputMeta,
            editableMap: self::buildEditableMap('weight', $weeks, $state->maxSets(), $state, $applicableWeeks),
        );

        return $rows;
    }

    private static function buildWeekColumn(string $setting, array $config, int $weeks, GridState $state, array $applicableWeeks, array $applicableSessions, array $sessionCounts, array $lockedWeekMap, array $lockedSessionsByWeek = [], ?GridOverrides $historicalOverrides = null): PreviewGridRow
    {
        $rawDefault = $config['default'] ?? null;
        $default = ($rawDefault === null || $rawDefault === '') ? '-' : $rawDefault;
        $cells = [];
        $overrideMap = [];
        $sessionCells = [];
        $sessionOverrideMap = [];

        for ($week = 0; $week < $weeks; $week++) {
            if (! ($applicableWeeks[$week] ?? true)) {
                $showsHistoricalOverride = ($lockedWeekMap[$week] ?? false) && self::hasHistoricalWeekOverride($historicalOverrides, $setting, $week);
                $fallbackValue = $state->getResolvedWeekValue($setting, $week, $default);
                $cells[$week] = $showsHistoricalOverride
                    ? self::getHistoricalWeekValue($historicalOverrides, $setting, $week, $fallbackValue)
                    : (($lockedWeekMap[$week] ?? false) ? $fallbackValue : '-');
                $overrideMap[$week] = $showsHistoricalOverride;
            } else {
                $cells[$week] = $state->getResolvedWeekValue($setting, $week, $default);
                $overrideMap[$week] = $state->isWeekOverridden($setting, $week);
            }

            for ($session = 0; $session < ($sessionCounts[$week] ?? 1); $session++) {
                if (! ($applicableSessions[$week][$session] ?? true)) {
                    $sessionShowsHistoricalOverride = ($lockedWeekMap[$week] ?? false) && self::hasHistoricalWeekOverride($historicalOverrides, $setting, $week);
                    $sessionFallbackValue = $state->getResolvedWeekValue($setting, $week, $default);
                    $sessionCells[$week][$session][0] = $sessionShowsHistoricalOverride
                        ? self::getHistoricalWeekValue($historicalOverrides, $setting, $week, $sessionFallbackValue)
                        : ((($lockedWeekMap[$week] ?? false) && ($lockedSessionsByWeek[$week][$session] ?? false)) ? $sessionFallbackValue : '-');
                    $sessionOverrideMap[$week][$session][0] = $sessionShowsHistoricalOverride;

                    continue;
                }

                $sessionCells[$week][$session][0] = $state->getResolvedWeekValue($setting, $week, $default);
                $sessionOverrideMap[$week][$session][0] = $state->isWeekOverridden($setting, $week);
            }
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
            clickField: Str::snake($setting),
            cells: $cells,
            sessionCells: $sessionCells,
            overrideColor: self::WEEK_COLUMN_OVERRIDE_COLOR,
            overrides: $overrideMap,
            sessionOverrides: $sessionOverrideMap,
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
    private static function buildApplicableWeekMap(int $weeks, ?string $startsAtDate, array $weekSessionDates, array $lockedSessionsByWeek = []): array
    {
        $applicableWeeks = [];

        for ($week = 0; $week < $weeks; $week++) {
            $lockedSessions = collect($lockedSessionsByWeek[$week] ?? [])
                ->contains(fn (mixed $locked): bool => (bool) $locked);

            if ($lockedSessions && self::allWeekSessionsLocked($week, $weekSessionDates, $lockedSessionsByWeek)) {
                $applicableWeeks[$week] = false;

                continue;
            }

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

    private static function hasHistoricalCellOverride(?GridOverrides $historicalOverrides, string $setting, int $week, int $set, ?int $session = null): bool
    {
        return $historicalOverrides?->hasCellOverride($week, $set, $setting, $session) ?? false;
    }

    private static function getHistoricalCellValue(?GridOverrides $historicalOverrides, string $setting, int $week, int $set, ?int $session = null): mixed
    {
        return $historicalOverrides?->getCellOverrideValue($week, $set, $setting, $session);
    }

    private static function hasHistoricalWeekOverride(?GridOverrides $historicalOverrides, string $setting, int $week): bool
    {
        return $historicalOverrides?->hasWeekOverride($week, $setting) ?? false;
    }

    private static function getHistoricalWeekValue(?GridOverrides $historicalOverrides, string $setting, int $week, mixed $default): mixed
    {
        return $historicalOverrides?->getWeekOverrideValue($week, $setting) ?? $default;
    }

    /** @return array<int, int> */
    private static function buildSessionCountMap(int $weeks, int $sessionsPerWeek, array $weekSessionDates, array $explicitWeekSessionCounts = []): array
    {
        $counts = [];

        for ($week = 0; $week < $weeks; $week++) {
            $explicitCount = (int) ($explicitWeekSessionCounts[$week] ?? 0);

            if ($explicitCount > 0) {
                $counts[$week] = max(
                    count($weekSessionDates[$week] ?? []),
                    $explicitCount,
                    1,
                );

                continue;
            }

            $counts[$week] = max(count($weekSessionDates[$week] ?? []), $sessionsPerWeek, 1);
        }

        return $counts;
    }

    /** @return array<int, array<int, bool>> */
    private static function buildApplicableSessionMap(int $weeks, int $sessionsPerWeek, ?string $startsAtDate, array $weekSessionDates, array $explicitWeekSessionCounts = [], array $lockedSessionsByWeek = []): array
    {
        $applicableSessions = [];
        $sessionCounts = self::buildSessionCountMap($weeks, $sessionsPerWeek, $weekSessionDates, $explicitWeekSessionCounts);

        for ($week = 0; $week < $weeks; $week++) {
            for ($session = 0; $session < $sessionCounts[$week]; $session++) {
                if (($lockedSessionsByWeek[$week][$session] ?? false) === true) {
                    $applicableSessions[$week][$session] = false;

                    continue;
                }

                if ($startsAtDate === null || $startsAtDate === '') {
                    $applicableSessions[$week][$session] = true;

                    continue;
                }

                $sessionDate = $weekSessionDates[$week][$session] ?? null;
                $applicableSessions[$week][$session] = ! is_string($sessionDate) || $sessionDate >= $startsAtDate;
            }
        }

        return $applicableSessions;
    }

    private static function allWeekSessionsLocked(int $week, array $weekSessionDates, array $lockedSessionsByWeek): bool
    {
        $sessionCount = max(
            count($weekSessionDates[$week] ?? []),
            count($lockedSessionsByWeek[$week] ?? []),
            1,
        );

        for ($session = 0; $session < $sessionCount; $session++) {
            if (! (($lockedSessionsByWeek[$week][$session] ?? false) === true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{
     *     array<int, array<int, string>>,
     *     array<int, array<int, string>>,
     *     array<int, array<int, array<int, string>>>,
     *     array<int, array<int, array<int, string>>>
     * }
     */
    private static function buildCellColorMaps(string $setting, array $cells, array $sessionCells, GridState $state): array
    {
        $colorMap = [];
        $overrideColorMap = [];
        $sessionColorMap = [];
        $sessionOverrideColorMap = [];

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

        foreach ($sessionCells as $week => $sessions) {
            foreach ($sessions as $session => $weekCells) {
                foreach ($weekCells as $set => $value) {
                    $sessionDerivedColor = self::resolveSessionDerivedColor($setting, $state, $week, $session, $set);
                    $sessionDerivedOverrideColor = self::resolveSessionDerivedOverrideColor($setting, $state, $week, $session, $set);

                    $color = $state->getSessionCellColor($setting, $week, $session, $set)
                        ?? $sessionDerivedColor
                        ?? $state->getCellColor($setting, $week, $set)
                        ?? $state->getCellColorByValue($setting, $value);

                    if ($color !== null) {
                        $sessionColorMap[$week][$session][$set] = $color;
                    }

                    $overrideColor = $state->getSessionCellOverrideColor($setting, $week, $session, $set)
                        ?? $sessionDerivedOverrideColor
                        ?? $state->getCellOverrideColor($setting, $week, $set)
                        ?? $state->getCellOverrideColorByValue($setting, $value);

                    if ($overrideColor !== null) {
                        $sessionOverrideColorMap[$week][$session][$set] = $overrideColor;
                    }
                }
            }
        }

        return [$colorMap, $overrideColorMap, $sessionColorMap, $sessionOverrideColorMap];
    }

    private static function resolveSessionDerivedColor(string $setting, GridState $state, int $week, int $session, int $set): ?string
    {
        if ($setting !== 'heartRate') {
            return null;
        }

        $zone = $state->getResolvedCellValue('heartRateZone', $week, $set, $session);

        if ($zone === null) {
            return null;
        }

        return (new HeartRateZoneCellColors)->cellColor('heartRateZone', $zone);
    }

    private static function resolveSessionDerivedOverrideColor(string $setting, GridState $state, int $week, int $session, int $set): ?string
    {
        if ($setting !== 'heartRate') {
            return null;
        }

        $zone = $state->getResolvedCellValue('heartRateZone', $week, $set, $session);

        if ($zone === null) {
            return null;
        }

        $zoneColors = new HeartRateZoneCellColors;

        return $state->isCellOverridden('heartRateZone', $week, $set, $session)
            ? $zoneColors->cellOverrideColor('heartRateZone', $zone)
            : $zoneColors->cellColor('heartRateZone', $zone);
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
