<?php

namespace App\Training\Planning;

use App\Data\Exercise\ExerciseSetting;
use App\Data\Exercise\Preview\GridOverrides;
use App\Data\Exercise\Preview\GridState;
use App\Data\Exercise\Preview\StrategyOrchestrator;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Training\Config\ExerciseOverrides;
use App\Data\Training\Planned\ResolvedPlannedExercise;
use App\Data\Training\Planned\ResolvedPlannedProvenance;
use App\Data\Training\Planned\ResolvedPlannedSession;
use App\Data\Training\Planned\ResolvedPlannedSet;
use App\Data\Training\Planned\ResolvedPlannedValue;
use App\Support\Profiling\PlanGridProfiler;
use App\Support\Training\ApplyPerScope;

class ResolvedPlannedSessionBuilder
{
    private const SETTING_PRIORITY = [
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
        'note',
    ];

    /**
     * @param  array<int, array{
     *     exerciseId:int|null,
     *     sort:int,
     *     group:string|null,
     *     type:string,
     *     effectiveConfig:array,
     *     overrideLayer:array{sessions?:array, cells?:array},
     *     baseConfig?:array,
     *     defaultOverrides?:?ExerciseOverrides,
     *     userOverrides?:?ExerciseOverrides,
     *     measuredData:?WeightProgressionSetting,
     *     maxHR:?int,
     *     iatPercent:?int
     * }>  $exerciseConfigs
     * @param  array<int, int>  $sessionCounts
     */
    public function build(
        int $weekIndex,
        int $sessionIndex,
        string $scheduledDate,
        array $exerciseConfigs,
        int $weeks,
        array $sessionCounts = [],
    ): ResolvedPlannedSession {
        $span = PlanGridProfiler::start('ResolvedPlannedSessionBuilder.build', [
            'week' => $weekIndex,
            'session' => $sessionIndex,
            'exercise_config_count' => count($exerciseConfigs),
            'weeks' => $weeks,
            'session_count_total' => array_sum($sessionCounts),
        ]);

        try {
            $exercises = array_values(array_filter(array_map(
                fn (array $exercise): ?ResolvedPlannedExercise => $this->buildExercise(
                    exerciseId: $exercise['exerciseId'] ?? null,
                    sort: (int) ($exercise['sort'] ?? 0),
                    group: $exercise['group'] ?? null,
                    type: (string) ($exercise['type'] ?? 'main'),
                    effectiveConfig: $exercise['effectiveConfig'] ?? [],
                    overrideLayer: $exercise['overrideLayer'] ?? ['sessions' => [], 'cells' => []],
                    baseConfig: $exercise['baseConfig'] ?? [],
                    defaultOverrides: $exercise['defaultOverrides'] ?? null,
                    userOverrides: $exercise['userOverrides'] ?? null,
                    weekIndex: $weekIndex,
                    sessionIndex: $sessionIndex,
                    weeks: $weeks,
                    sessionCounts: $sessionCounts,
                    measuredData: $exercise['measuredData'] ?? null,
                    maxHR: $exercise['maxHR'] ?? null,
                    iatPercent: $exercise['iatPercent'] ?? null,
                ),
                $exerciseConfigs,
            )));

            return new ResolvedPlannedSession(
                weekIndex: $weekIndex,
                sessionIndex: $sessionIndex,
                scheduledDate: $scheduledDate,
                exercises: $exercises,
            );
        } finally {
            PlanGridProfiler::end($span, [
                'resolved_exercise_count' => isset($exercises) ? count($exercises) : null,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $sessionCounts
     * @param  array{sessions?:array, cells?:array}  $overrideLayer
     */
    public function buildExercise(
        ?int $exerciseId,
        int $sort,
        ?string $group,
        string $type,
        array $effectiveConfig,
        array $overrideLayer,
        int $weekIndex,
        int $sessionIndex,
        int $weeks,
        array $sessionCounts = [],
        ?WeightProgressionSetting $measuredData = null,
        ?int $maxHR = null,
        ?int $iatPercent = null,
        array $baseConfig = [],
        ?ExerciseOverrides $defaultOverrides = null,
        ?ExerciseOverrides $userOverrides = null,
    ): ?ResolvedPlannedExercise {
        $span = PlanGridProfiler::start('ResolvedPlannedSessionBuilder.buildExercise', [
            'exercise_id' => $exerciseId,
            'week' => $weekIndex,
            'session' => $sessionIndex,
            'weeks' => $weeks,
            'settings' => $effectiveConfig['settings'] ?? [],
            'override_cells' => count($overrideLayer['cells'] ?? []),
            'override_sessions' => count($overrideLayer['sessions'] ?? []),
            'has_default_overrides' => $defaultOverrides !== null,
            'has_user_overrides' => $userOverrides !== null,
        ]);

        try {
            $state = (new StrategyOrchestrator(
                data: $effectiveConfig,
                measuredData: $measuredData,
                weeks: $weeks,
                overrides: GridOverrides::fromConfig($overrideLayer),
                maxHR: $maxHR,
                iatPercent: $iatPercent,
                sessionCounts: $sessionCounts,
            ))->execute();

            $setCount = max(0, (int) $state->getResolvedSessionValue(
                'sets',
                $weekIndex,
                $sessionIndex,
                (int) ($state->getSetsPerWeek()[$weekIndex] ?? data_get($effectiveConfig, 'sets.default', 0)),
            ));
            $setCountProvenance = $this->resolveSessionValueProvenance(
                setting: 'sets',
                weekIndex: $weekIndex,
                sessionIndex: $sessionIndex,
                setIndex: null,
                effectiveConfig: $effectiveConfig,
                effectiveGridOverrides: $overrideLayer,
                baseConfig: $baseConfig,
                defaultOverrides: $defaultOverrides,
                userOverrides: $userOverrides,
            );

            $sets = [];
            for ($setIndex = 0; $setIndex < $setCount; $setIndex++) {
                $values = [];

                foreach ($this->orderedSettings($effectiveConfig['settings'] ?? []) as $setting) {
                    $config = $effectiveConfig[$setting] ?? [];
                    $applyPer = ApplyPerScope::normalize($config['applyPer'] ?? null);
                    $value = $applyPer === ApplyPerScope::SESSION
                        ? $this->resolveSessionFieldValue($state, $config, $setting, $weekIndex, $sessionIndex)
                        : $this->resolveSessionValue($state, $config, $setting, $weekIndex, $setIndex, $sessionIndex);

                    if ($this->isBlankValue($value) && ! $this->shouldMaterializeBlankSetting($config)) {
                        continue;
                    }

                    $values[] = new ResolvedPlannedValue(
                        settingKey: $setting,
                        value: $value,
                        unit: $this->resolveUnit($setting, $config),
                        applyPer: $applyPer,
                        provenance: $applyPer === ApplyPerScope::SESSION
                            ? $this->resolveSessionValueProvenance($setting, $weekIndex, $sessionIndex, null, $effectiveConfig, $overrideLayer, $baseConfig, $defaultOverrides, $userOverrides)
                            : $this->resolveSessionValueProvenance($setting, $weekIndex, $sessionIndex, $setIndex, $effectiveConfig, $overrideLayer, $baseConfig, $defaultOverrides, $userOverrides),
                    );
                }

                $sets[] = new ResolvedPlannedSet(
                    setNumber: $setIndex + 1,
                    values: $values,
                );
            }

            return new ResolvedPlannedExercise(
                exerciseId: $exerciseId,
                sort: $sort,
                group: $group,
                type: $type,
                sets: $sets,
                setCountProvenance: $setCountProvenance,
            );
        } finally {
            PlanGridProfiler::end($span, [
                'set_count' => $setCount ?? null,
                'value_count' => isset($sets) ? collect($sets)->sum(fn (ResolvedPlannedSet $set): int => count($set->values)) : null,
            ]);
        }
    }

    private function resolveSessionValue(GridState $state, array $config, string $setting, int $weekIndex, int $setIndex, int $sessionIndex): mixed
    {
        $resolved = $state->getResolvedCellValue($setting, $weekIndex, $setIndex, $sessionIndex);

        return $resolved ?? $this->defaultValue($config);
    }

    private function resolveSessionFieldValue(GridState $state, array $config, string $setting, int $weekIndex, int $sessionIndex): mixed
    {
        return $state->getResolvedSessionValue($setting, $weekIndex, $sessionIndex, $this->defaultValue($config));
    }

    private function defaultValue(array $config): mixed
    {
        $default = $config['default'] ?? null;

        return $this->isBlankValue($default) ? null : $default;
    }

    private function isBlankValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === '-' || $value === '—';
    }

    private function shouldMaterializeBlankSetting(array $config): bool
    {
        return ($config['mode'] ?? 'manual') === 'manual';
    }

    /**
     * @param  string[]  $settings
     * @return string[]
     */
    private function orderedSettings(array $settings): array
    {
        usort($settings, function (string $a, string $b): int {
            $priorityA = array_search($a, self::SETTING_PRIORITY, true);
            $priorityB = array_search($b, self::SETTING_PRIORITY, true);

            return ($priorityA === false ? PHP_INT_MAX : $priorityA)
                <=> ($priorityB === false ? PHP_INT_MAX : $priorityB);
        });

        return array_values($settings);
    }

    private function resolveUnit(string $setting, array $config): ?string
    {
        $enum = ExerciseSetting::tryFrom($setting);
        $settingClass = $enum?->settingClass();

        return $settingClass ? $settingClass::resolveUnitLabel($config) : null;
    }

    private function resolveSessionValueProvenance(
        string $setting,
        int $weekIndex,
        int $sessionIndex,
        ?int $setIndex,
        array $effectiveConfig,
        array $effectiveGridOverrides,
        array $baseConfig = [],
        ?ExerciseOverrides $defaultOverrides = null,
        ?ExerciseOverrides $userOverrides = null,
    ): ResolvedPlannedProvenance {
        $userGridOverrides = $userOverrides?->gridOverrides ?? ['sessions' => [], 'cells' => []];
        $defaultGridOverrides = $defaultOverrides?->gridOverrides ?? ['sessions' => [], 'cells' => []];
        $exerciseGridOverrides = $baseConfig['overrides'] ?? $effectiveGridOverrides;

        if ($setIndex !== null) {
            if ($this->hasCellOverride($userGridOverrides, $weekIndex, $sessionIndex, $setIndex, $setting)) {
                return new ResolvedPlannedProvenance(kind: 'grid_override', layer: 'user');
            }

            if ($this->hasCellOverride($defaultGridOverrides, $weekIndex, $sessionIndex, $setIndex, $setting)) {
                return new ResolvedPlannedProvenance(kind: 'grid_override', layer: 'plan');
            }

            if ($this->hasCellOverride($exerciseGridOverrides, $weekIndex, $sessionIndex, $setIndex, $setting)) {
                return new ResolvedPlannedProvenance(kind: 'grid_override', layer: 'exercise');
            }
        } else {
            if ($this->hasSessionOverride($userGridOverrides, $weekIndex, $sessionIndex, $setting)) {
                return new ResolvedPlannedProvenance(kind: 'grid_override', layer: 'user');
            }

            if ($this->hasSessionOverride($defaultGridOverrides, $weekIndex, $sessionIndex, $setting)) {
                return new ResolvedPlannedProvenance(kind: 'grid_override', layer: 'plan');
            }

            if ($this->hasSessionOverride($exerciseGridOverrides, $weekIndex, $sessionIndex, $setting)) {
                return new ResolvedPlannedProvenance(kind: 'grid_override', layer: 'exercise');
            }
        }

        $configLayer = $this->resolveConfigLayer($setting, $baseConfig, $defaultOverrides, $userOverrides);
        $kind = $this->resolveValueKind($setting, $effectiveConfig);

        return new ResolvedPlannedProvenance(kind: $kind, layer: $configLayer);
    }

    private function resolveConfigLayer(string $setting, array $baseConfig, ?ExerciseOverrides $defaultOverrides, ?ExerciseOverrides $userOverrides): string
    {
        if ($setting !== 'sets' && $this->hasSettingOverride($userOverrides, $setting)) {
            return 'user';
        }

        if ($setting !== 'sets' && $this->hasSettingOverride($defaultOverrides, $setting)) {
            return 'plan';
        }

        if ($setting === 'sets' && $userOverrides?->sets !== null) {
            return 'user';
        }

        if ($setting === 'sets' && $defaultOverrides?->sets !== null) {
            return 'plan';
        }

        return array_key_exists($setting, $baseConfig) ? 'exercise' : 'exercise';
    }

    private function hasSettingOverride(?ExerciseOverrides $overrides, string $setting): bool
    {
        return $overrides?->hasSettingOverride($setting) ?? false;
    }

    private function resolveValueKind(string $setting, array $effectiveConfig): string
    {
        if ($setting === 'sets') {
            return 'strategy';
        }

        $config = $effectiveConfig[$setting] ?? [];

        return (($config['mode'] ?? 'manual') === 'automatic' || $setting === 'oneRepMax')
            ? 'strategy'
            : 'config';
    }

    /** @param array{sessions?:array, cells?:array} $gridOverrides */
    private function hasCellOverride(array $gridOverrides, int $week, int $session, int $set, string $setting): bool
    {
        foreach ($gridOverrides['cells'] ?? [] as $override) {
            if (($override['week'] ?? null) === $week
                && ($override['session'] ?? null) === $session
                && ($override['set'] ?? null) === $set
                && isset($override['data'][$setting])) {
                return true;
            }
        }

        return false;
    }

    /** @param array{sessions?:array, cells?:array} $gridOverrides */
    private function hasSessionOverride(array $gridOverrides, int $week, int $session, string $setting): bool
    {
        foreach ($gridOverrides['sessions'] ?? [] as $override) {
            if (($override['week'] ?? null) === $week
                && ($override['session'] ?? null) === $session
                && isset($override['data'][$setting])) {
                return true;
            }
        }

        return false;
    }
}
