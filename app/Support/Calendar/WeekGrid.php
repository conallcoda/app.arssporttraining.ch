<?php

namespace App\Support\Calendar;

use Carbon\Carbon;

class WeekGrid
{
    /** @return array{title: string, headers: list<string>, rows: list<list<array{date: string, day: int, isCurrentMonth: bool, isToday: bool}>>, cols: int} */
    public static function build(Carbon $date, int $firstDay = 1): array
    {
        $start = $date->copy()->startOfWeek($firstDay);
        $end = $start->copy()->addDays(6);
        $today = Carbon::today();

        $days = [];
        $headers = [];
        $current = $start->copy();

        for ($i = 0; $i < 7; $i++) {
            $days[] = [
                'date' => $current->format('Y-m-d'),
                'day' => $current->day,
                'isCurrentMonth' => true,
                'isToday' => $current->isSameDay($today),
            ];
            $headers[] = $current->shortDayName.' '.$current->format('d/m');
            $current->addDay();
        }

        $title = self::formatWeekTitle($start, $end);

        return [
            'title' => $title,
            'headers' => $headers,
            'rows' => [$days],
            'cols' => 7,
        ];
    }

    private static function formatWeekTitle(Carbon $start, Carbon $end): string
    {
        if ($start->month === $end->month) {
            return $start->day.'–'.$end->day.' '.$end->format('M Y');
        }

        if ($start->year === $end->year) {
            return $start->day.' '.$start->shortMonthName.' – '.$end->day.' '.$end->format('M Y');
        }

        return $start->format('d M Y').' – '.$end->format('d M Y');
    }
}
