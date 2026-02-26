<?php

namespace App\Support\Calendar;

use Carbon\Carbon;

class DayGrid
{
    /** @return array{title: string, headers: list<string>, rows: list<list<array{date: string, day: int, isCurrentMonth: bool, isToday: bool}>>, cols: int} */
    public static function build(Carbon $date): array
    {
        $today = Carbon::today();

        return [
            'title' => $date->format('j M Y'),
            'headers' => [$date->dayName],
            'rows' => [
                [
                    [
                        'date' => $date->format('Y-m-d'),
                        'day' => $date->day,
                        'isCurrentMonth' => true,
                        'isToday' => $date->isSameDay($today),
                    ],
                ],
            ],
            'cols' => 1,
        ];
    }
}
