<?php

namespace App\Support;

use Carbon\Carbon;

class WeekOptions
{
    public static function generate(int $monthsBefore = 3, int $monthsAfter = 9): array
    {
        $options = [];

        $start = Carbon::now()->subMonths($monthsBefore)->startOfWeek(Carbon::MONDAY);
        $end = Carbon::now()->addMonths($monthsAfter)->endOfWeek(Carbon::SUNDAY);

        $current = $start->copy();

        while ($current->lte($end)) {
            $weekNumber = $current->isoWeek();
            $year = $current->isoWeekYear();
            $dateFormatted = $current->format('d.m.Y');

            $value = $current->format('Y-m-d');
            $label = "{$dateFormatted} - W{$weekNumber}";

            $options[$value] = $label;

            $current->addWeek();
        }

        return $options;
    }

    public static function getCurrentWeekValue(): string
    {
        return Carbon::now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
    }
}
