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
            $config['overrides'] ?? ['cells' => [], 'weeks' => []],
            $overrides->gridOverrides,
        );

        return $config;
    }

    /** @return array{cells: array, weeks: array} */
    public static function mergeGridOverrides(array ...$layers): array
    {
        $mergedCells = [];
        $mergedWeeks = [];

        foreach ($layers as $layer) {
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

            foreach ($layer['weeks'] ?? [] as $week) {
                $key = (string) $week['week'];

                if (! isset($mergedWeeks[$key])) {
                    $mergedWeeks[$key] = $week;
                } else {
                    $mergedWeeks[$key]['data'] = array_merge(
                        $mergedWeeks[$key]['data'] ?? [],
                        $week['data'] ?? [],
                    );
                }
            }
        }

        return [
            'cells' => array_values($mergedCells),
            'weeks' => array_values($mergedWeeks),
        ];
    }
}
