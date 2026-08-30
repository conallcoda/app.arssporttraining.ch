<?php

namespace App\Training\Planning;

use App\Data\Exercise\Preview\SessionGroupingMode;

class ExerciseSessionCoordinateResolver
{
    /**
     * @return array{week: int, session: int, usesChronologicalSessions: bool, usesGroupedSlotIndex: bool}
     */
    public function resolve(
        array $effectiveConfig,
        int $calendarWeekIndex,
        int $calendarSessionIndex,
        int $slotIndex,
        bool $useSlotIndexForGroupedSessions = false,
    ): array {
        $groupingMode = SessionGroupingMode::normalizeMode(
            (string) data_get($effectiveConfig, 'preview.groupingMode'),
        );
        $usesGroupedSlotIndex = $useSlotIndexForGroupedSessions
            && $groupingMode === SessionGroupingMode::Groups->value;
        $usesChronologicalSessions = $groupingMode === SessionGroupingMode::None->value
            || $usesGroupedSlotIndex;
        $groupSize = SessionGroupingMode::normalizeGroupSize(
            isset($effectiveConfig['preview']['groupSize'])
                ? (int) $effectiveConfig['preview']['groupSize']
                : null,
            $groupingMode,
        );

        return [
            'week' => match (true) {
                $usesGroupedSlotIndex => intdiv($slotIndex, $groupSize),
                $groupingMode === SessionGroupingMode::None->value => $this->resolveUngroupedSessionIndex(
                    $effectiveConfig,
                    $slotIndex,
                ),
                default => $calendarWeekIndex,
            },
            'session' => match (true) {
                $usesGroupedSlotIndex => $slotIndex % $groupSize,
                $usesChronologicalSessions => 0,
                default => $calendarSessionIndex,
            },
            'usesChronologicalSessions' => $usesChronologicalSessions,
            'usesGroupedSlotIndex' => $usesGroupedSlotIndex,
        ];
    }

    private function resolveUngroupedSessionIndex(array $effectiveConfig, int $slotIndex): int
    {
        $authoredSessionCount = $this->authoredUngroupedSessionCount($effectiveConfig);

        if ($slotIndex < $authoredSessionCount || $this->hasLongitudinalProgression($effectiveConfig)) {
            return $slotIndex;
        }

        return $slotIndex % $authoredSessionCount;
    }

    private function authoredUngroupedSessionCount(array $effectiveConfig): int
    {
        $lastOverrideIndex = -1;

        foreach (['sessions', 'cells'] as $overrideType) {
            foreach (data_get($effectiveConfig, 'overrides.'.$overrideType, []) as $override) {
                $lastOverrideIndex = max($lastOverrideIndex, (int) ($override['week'] ?? -1));
            }
        }

        return max(
            1,
            (int) data_get($effectiveConfig, 'preview.weeks', 1),
            $lastOverrideIndex + 1,
        );
    }

    private function hasLongitudinalProgression(array $effectiveConfig): bool
    {
        if ((string) data_get($effectiveConfig, 'sets.deload', 'none') !== 'none') {
            return true;
        }

        foreach ($effectiveConfig['settings'] ?? [] as $setting) {
            if ($setting === 'oneRepMax') {
                return true;
            }

            $mode = strtolower((string) data_get($effectiveConfig, $setting.'.mode', 'manual'));

            if ($setting !== 'heartRate' && str_starts_with($mode, 'automatic')) {
                return true;
            }
        }

        return false;
    }
}
