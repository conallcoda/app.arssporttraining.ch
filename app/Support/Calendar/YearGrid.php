<?php

namespace App\Support\Calendar;

use Carbon\Carbon;

class YearGrid
{
    /** @return array{title: string, headers: list<string>, rows: list<list<array{date: string, day: int, isCurrentMonth: bool, isToday: bool}>>, cols: int, months: list<string>} */
    public static function build(Carbon $date): array
    {
        $today = Carbon::today();
        $year = $date->year;
        $rows = [];
        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $monthDate = Carbon::create($year, $month, 1);
            $daysInMonth = $monthDate->daysInMonth;
            $months[] = $monthDate->format('M');
            $cells = [];

            for ($day = 1; $day <= 31; $day++) {
                if ($day <= $daysInMonth) {
                    $current = Carbon::create($year, $month, $day);
                    $cells[] = [
                        'date' => $current->format('Y-m-d'),
                        'day' => $day,
                        'isCurrentMonth' => true,
                        'isToday' => $current->isSameDay($today),
                        'disabled' => false,
                    ];
                } else {
                    $cells[] = [
                        'date' => '',
                        'day' => $day,
                        'isCurrentMonth' => false,
                        'isToday' => false,
                        'disabled' => true,
                    ];
                }
            }

            $rows[] = $cells;
        }

        $headers = array_map(fn (int $d) => (string) $d, range(1, 31));

        return [
            'title' => (string) $year,
            'headers' => $headers,
            'rows' => $rows,
            'cols' => 31,
            'months' => $months,
        ];
    }
}
