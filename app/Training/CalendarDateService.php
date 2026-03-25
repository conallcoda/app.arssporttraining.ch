<?php

namespace App\Training;

use App\Data\Training\Calendar\CalendarSettingsData;
use App\Support\WeekOptions;
use Carbon\Carbon;

class CalendarDateService
{
    /** @return array{Carbon, Carbon} */
    public function dateRange(CalendarSettingsData $settings, int $weekStartsOn, int $weekEndsOn): array
    {
        $date = Carbon::parse($settings->date);

        return match ($settings->period) {
            'month' => [$date->copy()->startOfMonth()->startOfWeek($weekStartsOn), $date->copy()->endOfMonth()->endOfWeek($weekEndsOn)],
            'week' => [$date->copy()->startOfWeek($weekStartsOn), $date->copy()->endOfWeek($weekEndsOn)],
            'day' => [$date->copy(), $date->copy()],
            'range' => [
                ($settings->start ? Carbon::parse($settings->start) : $date->copy())->startOfWeek($weekStartsOn),
                ($settings->end ? Carbon::parse($settings->end) : $date->copy()->addMonth())->endOfWeek($weekEndsOn),
            ],
            default => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
        };
    }

    public function buildDays(Carbon $start, Carbon $end): array
    {
        $days = [];
        $current = $start->copy();

        while ($current->lte($end)) {
            $days[] = [
                'date' => $current->format('Y-m-d'),
                'day' => $current->day,
                'label' => $current->format('D'),
                'isToday' => $current->isToday(),
                'oddWeek' => $current->isoWeek() % 2 !== 0,
                'monthLabel' => $current->format('M'),
            ];
            $current->addDay();
        }

        return $days;
    }

    public function buildWeeks(Carbon $start, Carbon $end): array
    {
        return WeekOptions::weekSpansForDateRange($start, $end);
    }

    public function buildMonths(Carbon $start, Carbon $end): array
    {
        $months = [];
        $current = $start->copy()->startOfDay();
        $endDate = $end->copy()->startOfDay();
        $currentMonth = null;

        while ($current->lte($endDate)) {
            $key = $current->format('Y-m');

            if ($currentMonth !== null && $currentMonth['key'] === $key) {
                $currentMonth['colspan']++;
            } else {
                if ($currentMonth !== null) {
                    $months[] = $currentMonth;
                }
                $currentMonth = [
                    'key' => $key,
                    'label' => $current->format('F Y'),
                    'colspan' => 1,
                ];
            }

            $current->addDay();
        }

        if ($currentMonth !== null) {
            $months[] = $currentMonth;
        }

        return $months;
    }

    public function formatTitle(CalendarSettingsData $settings, int $weekStartsOn, int $weekEndsOn): string
    {
        $date = Carbon::parse($settings->date);

        return match ($settings->period) {
            'month' => $date->format('F Y'),
            'week' => 'W'.$date->isoWeek().' '.$date->isoWeekYear().' · '.$date->copy()->startOfWeek($weekStartsOn)->format('d M').' – '.$date->copy()->endOfWeek($weekEndsOn)->format('d M'),
            'day' => $date->format('d.m.Y'),
            'range' => $this->rangeTitle($settings, $weekStartsOn, $weekEndsOn),
            default => $date->format('F Y'),
        };
    }

    protected function rangeTitle(CalendarSettingsData $settings, int $weekStartsOn, int $weekEndsOn): string
    {
        $date = $settings->date;
        $start = ($settings->start ? Carbon::parse($settings->start) : Carbon::parse($date))->startOfWeek($weekStartsOn);
        $end = ($settings->end ? Carbon::parse($settings->end) : $start->copy()->addMonth())->endOfWeek($weekEndsOn);

        return $start->format('d.m.Y').' – '.$end->format('d.m.Y');
    }
}
