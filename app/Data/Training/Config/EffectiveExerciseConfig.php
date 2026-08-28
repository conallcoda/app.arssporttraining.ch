<?php

namespace App\Data\Training\Config;

use App\Data\Exercise\DropSet;
use App\Data\Exercise\ExerciseConfig;

class EffectiveExerciseConfig
{
    private const SETTING_KEYS = ['sets', 'reps', 'weight', 'tempo', 'rest', 'distance', 'duration', 'heartRate', 'heartRateZone', 'pace', 'watts'];

    public static function resolve(
        ExerciseConfig $base,
        ExerciseOverrides $planOverrides,
        ?ExerciseOverrides $userOverrides = null,
    ): array {
        $config = $base->toArray();

        $config = self::applyOverrideLayer($config, $planOverrides, includeGridOverrides: false);

        if ($userOverrides?->inheritPlanGridOverrides !== false) {
            $config['overrides'] = self::mergeGridOverrides(
                $config['overrides'] ?? ['sessions' => [], 'cells' => []],
                self::withoutIgnoredPlanSessions(
                    $planOverrides->gridOverrides,
                    $userOverrides?->ignoredPlanGridOverrideSessions ?? [],
                ),
            );
        }

        if ($userOverrides !== null) {
            $config = self::applyOverrideLayer($config, $userOverrides);
        }

        $config['overrides'] = self::filterGridOverridesForConfig(
            $config['overrides'] ?? ['sessions' => [], 'cells' => []],
            $config,
        );

        return $config;
    }

    public static function resolveForLayer(
        ExerciseConfig $base,
        ?ExerciseOverrides $planOverrides = null,
        ?ExerciseOverrides $userOverrides = null,
    ): array {
        $layers = [$base->overrides];

        if ($userOverrides?->inheritPlanGridOverrides !== false) {
            $layers[] = self::withoutIgnoredPlanSessions(
                $planOverrides?->gridOverrides ?? ['sessions' => [], 'cells' => []],
                $userOverrides?->ignoredPlanGridOverrideSessions ?? [],
            );
        }

        $layers[] = $userOverrides?->gridOverrides;
        $layers = array_filter($layers);

        return self::mergeGridOverrides(...$layers);
    }

    /** @return array{sessions: array, cells: array} */
    public static function filterGridOverridesForConfig(array $gridOverrides, array $config): array
    {
        if (! DropSet::isEnabled($config)) {
            return $gridOverrides;
        }

        $expected = DropSet::expectedPartCount($config);

        return [
            'sessions' => self::filterDropSetOverrideEntries($gridOverrides['sessions'] ?? [], $expected),
            'cells' => self::filterDropSetOverrideEntries($gridOverrides['cells'] ?? [], $expected),
        ];
    }

    public static function resolveDisabled(
        ExerciseOverrides $planOverrides,
        ?ExerciseOverrides $userOverrides = null,
    ): bool {
        if ($userOverrides !== null && $userOverrides->disabled !== null) {
            return $userOverrides->disabled;
        }

        return $planOverrides->disabled ?? false;
    }

    private static function applyOverrideLayer(array $config, ExerciseOverrides $overrides, bool $includeGridOverrides = true): array
    {
        if ($overrides->settings !== null) {
            $config['settings'] = $overrides->settings;
        }

        if ($overrides->sessionGrouping !== null) {
            $preview = $config['preview'] ?? [];
            $preview['groupingMode'] = $overrides->sessionGrouping->mode;
            $preview['groupSize'] = $overrides->sessionGrouping->groupSize;
            $preview['copyValuesAutomatically'] = $overrides->sessionGrouping->copyValuesAutomatically;
            $config['preview'] = $preview;
        }

        foreach (self::SETTING_KEYS as $key) {
            if ($overrides->{$key} !== null) {
                $config[$key] = $overrides->{$key}->toArray();
            }
        }

        if ($includeGridOverrides) {
            $config['overrides'] = self::mergeGridOverrides(
                $config['overrides'] ?? ['sessions' => [], 'cells' => []],
                $overrides->gridOverrides,
            );
        }

        return $config;
    }

    /** @return array{sessions: array, cells: array} */
    public static function mergeGridOverrides(array ...$layers): array
    {
        $mergedSessions = [];
        $mergedCells = [];

        foreach ($layers as $layer) {
            foreach ($layer['sessions'] ?? [] as $session) {
                $key = $session['week'].'-'.$session['session'];

                if (! isset($mergedSessions[$key])) {
                    $mergedSessions[$key] = $session;
                } else {
                    $mergedSessions[$key]['data'] = array_merge(
                        $mergedSessions[$key]['data'] ?? [],
                        $session['data'] ?? [],
                    );
                }
            }

            foreach ($layer['cells'] ?? [] as $cell) {
                $key = $cell['week'].'-'.$cell['session'].'-'.$cell['set'];

                if (! isset($mergedCells[$key])) {
                    $mergedCells[$key] = $cell;
                } else {
                    $mergedCells[$key]['data'] = array_merge(
                        $mergedCells[$key]['data'] ?? [],
                        $cell['data'] ?? [],
                    );
                }
            }

        }

        return [
            'sessions' => array_values($mergedSessions),
            'cells' => array_values($mergedCells),
        ];
    }

    /** @param list<string> $ignoredSessions */
    public static function withoutIgnoredPlanSessions(array $gridOverrides, array $ignoredSessions): array
    {
        if ($ignoredSessions === []) {
            return $gridOverrides;
        }

        $ignored = array_fill_keys($ignoredSessions, true);
        $keep = static fn (array $row): bool => ! isset($ignored[((int) ($row['week'] ?? -1)).':'.((int) ($row['session'] ?? -1))]);

        return [
            'sessions' => array_values(array_filter($gridOverrides['sessions'] ?? [], $keep)),
            'cells' => array_values(array_filter($gridOverrides['cells'] ?? [], $keep)),
        ];
    }

    private static function filterDropSetOverrideEntries(array $entries, ?int $expected): array
    {
        $filtered = [];

        foreach ($entries as $entry) {
            $data = $entry['data'] ?? [];
            $entryExpected = $expected;

            if (array_key_exists('reps', $data)) {
                $repsCount = DropSet::partCount('reps', $data['reps']);

                if ($repsCount !== null) {
                    $entryExpected = $repsCount;
                }
            }

            foreach (['reps', 'weight', 'duration'] as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }

                $actual = DropSet::partCount($field, $data[$field]);

                if ($actual === null || ($entryExpected !== null && $actual !== $entryExpected)) {
                    unset($data[$field]);
                }
            }

            if ($data === []) {
                continue;
            }

            $entry['data'] = $data;
            $filtered[] = $entry;
        }

        return $filtered;
    }
}
