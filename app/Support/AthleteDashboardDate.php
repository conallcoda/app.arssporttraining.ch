<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class AthleteDashboardDate
{
    private const SCOPE_READINESS = 'readiness';

    private const SCOPE_PROGRAM_EXERCISES = 'programs.exercises';

    public static function today(): CarbonImmutable
    {
        $override = config('athlete.dashboard_today_override');

        if ($override === null || $override === '') {
            return CarbonImmutable::today();
        }

        return CarbonImmutable::createFromFormat('d.m.Y', $override)->startOfDay();
    }

    public static function todayDateString(): string
    {
        return static::today()->format('Y-m-d');
    }

    public static function isFutureDate(CarbonImmutable|string $date): bool
    {
        $resolvedDate = static::resolveDate($date);

        return $resolvedDate->gt(static::today()->startOfDay());
    }

    public static function canSubmitReadinessForDate(CarbonImmutable|string $date): bool
    {
        return static::canEditDate(self::SCOPE_READINESS, $date);
    }

    public static function canRecordProgramExercisesForDate(CarbonImmutable|string $date): bool
    {
        return static::canEditDate(self::SCOPE_PROGRAM_EXERCISES, $date);
    }

    public static function canEditDate(string $scope, CarbonImmutable|string $date): bool
    {
        $resolvedDate = static::resolveDate($date);
        $relation = static::dateRelation($resolvedDate);
        $scopedValue = config('athlete.editability.'.$scope.'.'.$relation);

        if (is_bool($scopedValue)) {
            return $scopedValue;
        }

        $globalValue = config('athlete.can_edit_all');

        if (is_bool($globalValue)) {
            return $globalValue;
        }

        if ($relation === 'future') {
            $editFuture = config('athlete.edit_future');

            if (is_bool($editFuture)) {
                return $editFuture;
            }
        }

        return static::defaultEditability($scope, $relation);
    }

    public static function dateRelation(CarbonImmutable|string $date): string
    {
        $resolvedDate = static::resolveDate($date);
        $today = static::today()->startOfDay();

        if ($resolvedDate->equalTo($today)) {
            return 'today';
        }

        return $resolvedDate->lt($today) ? 'past' : 'future';
    }

    private static function resolveDate(CarbonImmutable|string $date): CarbonImmutable
    {
        return $date instanceof CarbonImmutable
            ? $date->startOfDay()
            : CarbonImmutable::parse($date)->startOfDay();
    }

    private static function defaultEditability(string $scope, string $relation): bool
    {
        return match ($scope) {
            self::SCOPE_READINESS => match ($relation) {
                'past' => true,
                'today' => true,
                'future' => true,
                default => false,
            },
            self::SCOPE_PROGRAM_EXERCISES => match ($relation) {
                'past' => true,
                'today' => true,
                'future' => false,
                default => false,
            },
            default => false,
        };
    }

}
