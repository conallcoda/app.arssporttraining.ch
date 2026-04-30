<?php

namespace App\Data\Exercise\Preview;

use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Support\Training\GridOverrideNormalizer;

class OverrideManager
{
    /**
     * @param  array{sessions?: array, cells?: array, weeks?: array}  $overrides
     * @return array{sessions: array, cells: array}
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
        mixed $effectiveDefault = null,
        ?int $weekSessionCount = null,
    ): array {
        $overrides = GridOverrideNormalizer::normalize($overrides, $config, $weekSessionCount === null ? null : [$weekIndex => $weekSessionCount]);
        $defaultValue = $effectiveDefault ?? self::getDefaultCellValue($config, $defaultWeeks, $field, $weekIndex, $setIndex);
        $valuesMatch = self::valuesMatch($value, $defaultValue, $field);

        if ($valuesMatch) {
            return self::removeCellOverride($overrides, $weekIndex, $session, $setIndex, $field);
        }

        return self::setCellOverride($overrides, $weekIndex, $session, $setIndex, $field, $value);
    }

    /**
     * @param  array{sessions?: array, cells?: array, weeks?: array}  $overrides
     * @return array{sessions: array, cells: array}
     */
    public static function updateSessionOverride(
        array $overrides,
        array $config,
        int $weekIndex,
        int $session,
        string $field,
        mixed $value,
        mixed $effectiveDefault = null,
    ): array {
        $overrides = GridOverrideNormalizer::normalize($overrides, $config);
        $fieldConfig = $config[$field] ?? [];
        $defaultValue = $effectiveDefault ?? ($fieldConfig['default'] ?? null);
        $valuesMatch = self::valuesMatch($value, $defaultValue, $field);

        if ($valuesMatch) {
            return self::removeSessionOverride($overrides, $weekIndex, $session, $field);
        }

        return self::setSessionOverride($overrides, $weekIndex, $session, $field, $value);
    }

    /**
     * @param  array{sessions?: array, cells?: array, weeks?: array}  $overrides
     * @return array{sessions: array, cells: array}
     */
    public static function copySessionOverrides(
        array $overrides,
        PreviewGrid $grid,
        PreviewGrid $defaultsGrid,
        int $sourceWeek,
        int $sourceSession,
        int $targetWeek,
        int $targetSession,
    ): array {
        $overrides = GridOverrideNormalizer::normalize($overrides);
        $defaultRows = [];

        foreach ($defaultsGrid->rows as $row) {
            $defaultRows[$row->field] = $row;
        }

        foreach ($grid->rows as $row) {
            if ($row->lastSessionOnly) {
                continue;
            }

            for ($set = 0; $set < $grid->setCount; $set++) {
                $value = $row->getCellValue($sourceWeek, $set, $sourceSession);

                if ($value === null || $value === '-') {
                    $overrides = self::removeCellOverride($overrides, $targetWeek, $targetSession, $set, $row->field);

                    continue;
                }

                $defaultValue = $defaultRows[$row->field]->getCellValue($targetWeek, $set, $targetSession);

                if (self::valuesMatch($value, $defaultValue, $row->field)) {
                    $overrides = self::removeCellOverride($overrides, $targetWeek, $targetSession, $set, $row->field);
                } else {
                    $overrides = self::setCellOverride($overrides, $targetWeek, $targetSession, $set, $row->field, $value);
                }
            }
        }

        foreach ($grid->weekColumns as $column) {
            $value = $column->getCellValue($sourceWeek, 0, $sourceSession);
            $defaultValue = $column->getCellValue($targetWeek, 0, $targetSession);

            if ($value === null || $value === '-' || self::valuesMatch($value, $defaultValue, $column->field)) {
                $overrides = self::removeSessionOverride($overrides, $targetWeek, $targetSession, $column->field);
            } else {
                $overrides = self::setSessionOverride($overrides, $targetWeek, $targetSession, $column->field, $value);
            }
        }

        return $overrides;
    }

    /** @return array{sessions: array, cells: array} */
    public static function reset(): array
    {
        return ['sessions' => [], 'cells' => []];
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

    /** @return array{sessions: array, cells: array} */
    private static function setCellOverride(array $overrides, int $week, int $session, int $set, string $field, mixed $value): array
    {
        $overrides['cells'] = GridOverrideNormalizer::putCellValue(
            $overrides['cells'] ?? [],
            $week,
            $session,
            $set,
            $field,
            $value,
        );

        return $overrides;
    }

    /** @return array{sessions: array, cells: array} */
    private static function removeCellOverride(array $overrides, int $week, int $session, int $set, string $field): array
    {
        foreach ($overrides['cells'] ?? [] as $index => $override) {
            if (($override['week'] ?? null) !== $week
                || ($override['session'] ?? null) !== $session
                || ($override['set'] ?? null) !== $set
                || ! isset($override['data'][$field])) {
                continue;
            }

            unset($overrides['cells'][$index]['data'][$field]);

            if (empty($overrides['cells'][$index]['data'])) {
                unset($overrides['cells'][$index]);
            }

            $overrides['cells'] = array_values($overrides['cells']);

            return $overrides;
        }

        return $overrides;
    }

    /** @return array{sessions: array, cells: array} */
    private static function setSessionOverride(array $overrides, int $week, int $session, string $field, mixed $value): array
    {
        $overrides['sessions'] = GridOverrideNormalizer::putSessionValue(
            $overrides['sessions'] ?? [],
            $week,
            $session,
            $field,
            $value,
        );

        return $overrides;
    }

    /** @return array{sessions: array, cells: array} */
    private static function removeSessionOverride(array $overrides, int $week, int $session, string $field): array
    {
        foreach ($overrides['sessions'] ?? [] as $index => $override) {
            if (($override['week'] ?? null) !== $week
                || ($override['session'] ?? null) !== $session
                || ! isset($override['data'][$field])) {
                continue;
            }

            unset($overrides['sessions'][$index]['data'][$field]);

            if (empty($overrides['sessions'][$index]['data'])) {
                unset($overrides['sessions'][$index]);
            }

            $overrides['sessions'] = array_values($overrides['sessions']);

            return $overrides;
        }

        return $overrides;
    }

    private static function valuesMatch(mixed $value, mixed $originalValue, string $field): bool
    {
        if ($originalValue === null) {
            return false;
        }

        if (in_array($field, ['tempo', 'pace'], true) || is_string($value) || is_string($originalValue)) {
            return (string) $value === (string) $originalValue;
        }

        return abs((float) $value - (float) $originalValue) < 0.001;
    }
}
