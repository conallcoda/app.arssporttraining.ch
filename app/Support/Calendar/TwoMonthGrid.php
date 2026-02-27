<?php

namespace App\Support\Calendar;

use Carbon\Carbon;

class TwoMonthGrid
{
    /** @return array{title: string, headers: list<string>, rows: list<list<array{date: string, day: int, isCurrentMonth: bool, isToday: bool}>>, cols: int, grids: list<array{title: string, headers: list<string>, rows: list<list<array{date: string, day: int, isCurrentMonth: bool, isToday: bool}>>, cols: int}>} */
    public static function build(Carbon $date, int $firstDay = 1): array
    {
        $left = MonthGrid::build($date, $firstDay);
        $right = MonthGrid::build($date->copy()->addMonth(), $firstDay);

        $title = $date->format('F') === $date->copy()->addMonth()->format('F')
            ? $date->format('F Y')
            : $date->format('F').' – '.$date->copy()->addMonth()->format('F Y');

        return [
            'title' => $title,
            'headers' => $left['headers'],
            'rows' => [],
            'cols' => 7,
            'grids' => [$left, $right],
        ];
    }
}
