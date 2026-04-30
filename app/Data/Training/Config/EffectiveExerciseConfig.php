<?php

namespace App\Data\Training\Config;

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

        $config = self::applyOverrideLayer($config, $planOverrides);

        if ($userOverrides !== null) {
            $config = self::applyOverrideLayer($config, $userOverrides);
        }

        return $config;
    }

    public static function resolveForLayer(
        ExerciseConfig $base,
        ?ExerciseOverrides $planOverrides = null,
        ?ExerciseOverrides $userOverrides = null,
    ): array {
        $layers = array_filter([$base->overrides, $planOverrides?->gridOverrides, $userOverrides?->gridOverrides]);

        return self::mergeGridOverrides(...$layers);
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

    private static function applyOverrideLayer(array $config, ExerciseOverrides $overrides): array
    {
        if ($overrides->settings !== null) {
            $config['settings'] = $overrides->settings;
        }

        foreach (self::SETTING_KEYS as $key) {
            if ($overrides->{$key} !== null) {
                $config[$key] = $overrides->{$key}->toArray();
            }
        }

        $config['overrides'] = self::mergeGridOverrides(
            $config['overrides'] ?? ['sessions' => [], 'cells' => []],
            $overrides->gridOverrides,
        );

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
}
