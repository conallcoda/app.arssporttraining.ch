<?php

namespace App\Support\Calendar;

use Carbon\Carbon;

class GridBuilder
{
    /** @return array{title: string, headers: list<string>, rows: list<list<array{date: string, day: int, isCurrentMonth: bool, isToday: bool}>>, cols: int} */
    public static function build(Carbon $date, string $view, int $firstDay = 1): array
    {
        return match ($view) {
            'two-month' => TwoMonthGrid::build($date, $firstDay),
            'month' => MonthGrid::build($date, $firstDay),
            'week' => WeekGrid::build($date, $firstDay),
            'day' => DayGrid::build($date),
            'year' => YearGrid::build($date),
            default => MonthGrid::build($date, $firstDay),
        };
    }
}
