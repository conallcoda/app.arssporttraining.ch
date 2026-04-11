<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class WeekOptions
{
    public static function generate(int $monthsBefore = 3, int $monthsAfter = 9, ?int $weekStartsOn = null, CarbonInterface|string|null $referenceDate = null): array
    {
        $weekStartsOn ??= (int) config('training.week_starts_on', Carbon::MONDAY);
        $weekEndsOn = ($weekStartsOn + 6) % 7;
        $options = [];

        $reference = $referenceDate instanceof CarbonInterface
            ? Carbon::instance($referenceDate)
            : ($referenceDate ? Carbon::parse($referenceDate) : Carbon::now());

        $start = $reference->copy()->subMonths($monthsBefore)->startOfWeek($weekStartsOn);
        $end = $reference->copy()->addMonths($monthsAfter)->endOfWeek($weekEndsOn);

        $current = $start->copy();

        while ($current->lte($end)) {
            $weekNumber = $current->isoWeek();
            $year = $current->isoWeekYear();

            $value = $current->format('Y-m-d');
            $label = "Week {$weekNumber}, {$year}";

            $options[$value] = $label;

            $current->addWeek();
        }

        return $options;
    }

    public static function getCurrentWeekValue(?int $weekStartsOn = null, CarbonInterface|string|null $referenceDate = null): string
    {
        $weekStartsOn ??= (int) config('training.week_starts_on', Carbon::MONDAY);

        $reference = $referenceDate instanceof CarbonInterface
            ? Carbon::instance($referenceDate)
            : ($referenceDate ? Carbon::parse($referenceDate) : Carbon::now());

        return $reference->startOfWeek($weekStartsOn)->format('Y-m-d');
    }

    /** @return array<int, array{week: int, colspan: int}> */
    public static function weekSpansForMonth(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        return static::weekSpansForDateRange($start, $end);
    }

    /** @return array<int, array{week: int, colspan: int}> */
    public static function weekSpansForDateRange(Carbon $start, Carbon $end): array
    {
        $weeks = [];
        $currentWeek = null;
        $current = $start->copy()->startOfDay();
        $endDate = $end->copy()->startOfDay();

        while ($current->lte($endDate)) {
            $week = $current->isoWeek();

            if ($currentWeek !== null && $currentWeek['week'] === $week) {
                $currentWeek['colspan']++;
            } else {
                if ($currentWeek !== null) {
                    $weeks[] = $currentWeek;
                }
                $currentWeek = ['week' => $week, 'colspan' => 1];
            }

            $current->addDay();
        }

        if ($currentWeek !== null) {
            $weeks[] = $currentWeek;
        }

        return $weeks;
    }
}
