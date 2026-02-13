<?php

namespace App\Data\Exercise\Preview;

use App\Data\Exercise\Settings\WeightProgressionSetting;

class OverrideManager
{
    /**
     * @param  array{cells: array, weeks: array}  $overrides
     * @return array{cells: array, weeks: array}
     */
    public static function updateCellOverride(
        array $overrides,
        array $config,
        int $defaultWeeks,
        int $defaultSessionsPerWeek,
        int $weekIndex,
        int $setIndex,
        string $field,
        mixed $value,
        int $session,
        bool $applyToAll = false,
    ): array {
        $defaultValue = self::getDefaultCellValue($config, $defaultWeeks, $field, $weekIndex, $setIndex);
        $valuesMatch = self::cellValuesMatch($value, $defaultValue);

        $sessionsPerWeek = (int) ($config['preview']['sessionsPerWeek'] ?? $defaultSessionsPerWeek);
        $sessions = $applyToAll ? range(0, $sessionsPerWeek - 1) : [$session];

        foreach ($sessions as $s) {
            if ($valuesMatch) {
                $overrides = self::removeCellOverride($overrides, $weekIndex, $s, $setIndex, $field);
            } else {
                $overrides = self::setCellOverride($overrides, $weekIndex, $s, $setIndex, $field, $value);
            }
        }

        return $overrides;
    }

    /**
     * @param  array{cells: array, weeks: array}  $overrides
     * @return array{cells: array, weeks: array}
     */
    public static function updateWeekOverride(
        array $overrides,
        array $config,
        int $weekIndex,
        string $field,
        mixed $value,
    ): array {
        $fieldConfig = $config[$field] ?? [];
        $defaultValue = $fieldConfig['default'] ?? null;
        $valuesMatch = self::weekValuesMatch($value, $defaultValue, $field);

        if ($valuesMatch) {
            return self::removeWeekOverride($overrides, $weekIndex, $field);
        }

        return self::setWeekOverride($overrides, $weekIndex, $field, $value);
    }

    /** @return array{cells: array, weeks: array} */
    public static function reset(): array
    {
        return ['cells' => [], 'weeks' => []];
    }

    public static function getDefaultCellValue(array $config, int $defaultWeeks, string $field, int $weekIndex, int $setIndex): mixed
    {
        $preview = $config['preview'] ?? [];
        $measuredData = new WeightProgressionSetting(
            measuredReps: $preview['measuredReps'] ?? null,
            measuredWeight: $preview['measuredWeight'] ?? null,
            targetGoal: $preview['targetGoal'] ?? null,
        );

        $weeks = (int) ($preview['weeks'] ?? $defaultWeeks);
        $orchestrator = new StrategyOrchestrator($config, $measuredData, $weeks);
        $state = $orchestrator->execute();

        return $state->getCellValue($field, $weekIndex, $setIndex);
    }

    /**
     * @param  array{cells: array, weeks: array}  $overrides
     * @return array{cells: array, weeks: array}
     */
    private static function setCellOverride(array $overrides, int $week, int $session, int $set, string $field, mixed $value): array
    {
        $cells = $overrides['cells'] ?? [];

        foreach ($cells as $index => $override) {
            if ($override['week'] === $week && $override['session'] === $session && $override['set'] === $set) {
                $overrides['cells'][$index]['data'][$field] = $value;

                return $overrides;
            }
        }

        $overrides['cells'][] = [
            'week' => $week,
            'session' => $session,
            'set' => $set,
            'data' => [$field => $value],
        ];

        return $overrides;
    }

    /**
     * @param  array{cells: array, weeks: array}  $overrides
     * @return array{cells: array, weeks: array}
     */
    private static function removeCellOverride(array $overrides, int $week, int $session, int $set, string $field): array
    {
        $cells = $overrides['cells'] ?? [];

        foreach ($cells as $index => $override) {
            if ($override['week'] === $week && $override['session'] === $session && $override['set'] === $set && isset($override['data'][$field])) {
                unset($overrides['cells'][$index]['data'][$field]);

                if (empty($overrides['cells'][$index]['data'])) {
                    array_splice($overrides['cells'], $index, 1);
                }

                return $overrides;
            }
        }

        return $overrides;
    }

    /**
     * @param  array{cells: array, weeks: array}  $overrides
     * @return array{cells: array, weeks: array}
     */
    private static function setWeekOverride(array $overrides, int $week, string $field, mixed $value): array
    {
        $weeks = $overrides['weeks'] ?? [];

        foreach ($weeks as $index => $override) {
            if ($override['week'] === $week) {
                $overrides['weeks'][$index]['data'][$field] = $value;

                return $overrides;
            }
        }

        $overrides['weeks'][] = [
            'week' => $week,
            'data' => [$field => $value],
        ];

        return $overrides;
    }

    /**
     * @param  array{cells: array, weeks: array}  $overrides
     * @return array{cells: array, weeks: array}
     */
    private static function removeWeekOverride(array $overrides, int $week, string $field): array
    {
        $weeks = $overrides['weeks'] ?? [];

        foreach ($weeks as $index => $override) {
            if ($override['week'] === $week && isset($override['data'][$field])) {
                unset($overrides['weeks'][$index]['data'][$field]);

                if (empty($overrides['weeks'][$index]['data'])) {
                    array_splice($overrides['weeks'], $index, 1);
                }

                return $overrides;
            }
        }

        return $overrides;
    }

    private static function cellValuesMatch(mixed $value, mixed $originalValue): bool
    {
        if ($originalValue === null) {
            return false;
        }

        if (is_string($value) || is_string($originalValue)) {
            return (string) $value === (string) $originalValue;
        }

        return abs((float) $value - (float) $originalValue) < 0.001;
    }

    private static function weekValuesMatch(mixed $value, mixed $originalValue, string $field): bool
    {
        if ($originalValue === null) {
            return false;
        }

        if (in_array($field, ['tempo', 'pace'])) {
            return (string) $value === (string) $originalValue;
        }

        return abs((float) $value - (float) $originalValue) < 0.001;
    }
}
