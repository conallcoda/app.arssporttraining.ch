<?php

namespace App\Support\Training;

class WeekSessionCountResolver
{
    /**
     * @param  array<int, int>  $weekSessions
     * @param  array<int, array<int, mixed>>  $weekSessionDates
     * @param  array<int, array<int, bool>>  $lockedSessionsByWeek
     */
    public static function resolveForWeek(
        int $weekIndex,
        int $fallbackSessionsPerWeek,
        array $weekSessions = [],
        array $weekSessionDates = [],
        array $lockedSessionsByWeek = [],
    ): int {
        $explicitSessions = (int) ($weekSessions[$weekIndex] ?? 0);
        $datedSessions = count($weekSessionDates[$weekIndex] ?? []);
        $lockedSessions = count($lockedSessionsByWeek[$weekIndex] ?? []);

        if ($explicitSessions > 0 || $datedSessions > 0 || $lockedSessions > 0) {
            return max($explicitSessions, $datedSessions, $lockedSessions, 1);
        }

        return max($fallbackSessionsPerWeek, 1);
    }

    /**
     * @param  array<int, int>  $weekSessions
     * @param  array<int, array<int, mixed>>  $weekSessionDates
     * @param  array<int, array<int, bool>>  $lockedSessionsByWeek
     * @return array<int, int>
     */
    public static function resolveForWeeks(
        int $weeks,
        int $fallbackSessionsPerWeek,
        array $weekSessions = [],
        array $weekSessionDates = [],
        array $lockedSessionsByWeek = [],
    ): array {
        $counts = [];

        for ($weekIndex = 0; $weekIndex < max($weeks, 1); $weekIndex++) {
            $counts[$weekIndex] = self::resolveForWeek(
                weekIndex: $weekIndex,
                fallbackSessionsPerWeek: $fallbackSessionsPerWeek,
                weekSessions: $weekSessions,
                weekSessionDates: $weekSessionDates,
                lockedSessionsByWeek: $lockedSessionsByWeek,
            );
        }

        return $counts;
    }
}
