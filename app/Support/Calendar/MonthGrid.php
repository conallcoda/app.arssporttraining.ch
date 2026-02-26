<?php

namespace App\Support\Calendar;

use Carbon\Carbon;

class MonthGrid
{
    /** @return array{title: string, headers: list<string>, rows: list<list<array{date: string, day: int, isCurrentMonth: bool, isToday: bool}>>, cols: int} */
    public static function build(Carbon $date, int $firstDay = 1): array
    {
        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();

        $gridStart = $start->copy()->startOfWeek($firstDay);
        $gridEnd = $end->copy()->endOfWeek($firstDay);

        $today = Carbon::today();
        $rows = [];
        $current = $gridStart->copy();

        while ($current->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = [
                    'date' => $current->format('Y-m-d'),
                    'day' => $current->day,
                    'isCurrentMonth' => $current->month === $date->month,
                    'isToday' => $current->isSameDay($today),
                ];
                $current->addDay();
            }
            $rows[] = $week;
        }

        return [
            'title' => $date->format('F Y'),
            'headers' => self::weekdayHeaders($firstDay, 'short'),
            'rows' => $rows,
            'cols' => 7,
        ];
    }

    /** @return list<string> */
    private static function weekdayHeaders(int $firstDay, string $format): array
    {
        $headers = [];
        $day = Carbon::now()->startOfWeek($firstDay);
        for ($i = 0; $i < 7; $i++) {
            $headers[] = $format === 'short' ? $day->shortDayName : $day->dayName;
            $day->addDay();
        }

        return $headers;
    }
}
