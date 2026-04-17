<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class AthleteDashboardDate
{
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
        $resolvedDate = $date instanceof CarbonImmutable
            ? $date->startOfDay()
            : CarbonImmutable::parse($date)->startOfDay();

        return $resolvedDate->gt(static::today()->startOfDay());
    }
}
