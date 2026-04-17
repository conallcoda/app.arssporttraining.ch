<?php

namespace App\Training;

use App\Data\Training\Calendar\CalendarSettingsData;
use App\Support\WeekOptions;
use Carbon\Carbon;
use Flux\DateRangePreset;

class CalendarDateService
{
    public const MAX_RANGE_MONTHS = 6;

    public const PRESET_CUSTOM = 'custom';

    public const PRESET_THIS_WEEK = 'thisWeek';

    public const PRESET_THIS_MONTH = 'thisMonth';

    public const PRESET_LAST_MONTH = 'lastMonth';

    public const PRESET_LAST_7_DAYS = 'last7Days';

    public const PRESET_LAST_30_DAYS = 'last30Days';

    public const PRESET_THIS_QUARTER = 'thisQuarter';

    public const PRESET_LAST_QUARTER = 'lastQuarter';

    public const PRESET_NEXT_QUARTER = 'nextQuarter';

    public const PRESET_NEXT_MONTH = 'nextMonth';

    public const PRESET_NEXT_7_DAYS = 'next7Days';

    public const PRESET_NEXT_30_DAYS = 'next30Days';

    public const PRESET_NEXT_3_MONTHS = 'next3Months';

    public const PRESET_NEXT_6_MONTHS = 'next6Months';

    /** @return array{Carbon, Carbon} */
    public function dateRange(CalendarSettingsData $settings, int $weekStartsOn, int $weekEndsOn): array
    {
        [$selectedStart, $selectedEnd] = self::isConcretePreset($settings->preset)
            ? $this->presetRange($settings->preset)
            : $this->normalizeSelectedRange($settings->start, $settings->end);

        return [
            $selectedStart->copy()->startOfWeek($weekStartsOn),
            $selectedEnd->copy()->endOfWeek($weekEndsOn),
        ];
    }

    /** @return array{Carbon, Carbon} */
    public function normalizeSelectedRange(?string $start, ?string $end): array
    {
        $selectedStart = Carbon::parse($start ?? now()->format('Y-m-d'))->startOfDay();
        $selectedEnd = Carbon::parse($end ?: $selectedStart->format('Y-m-d'))->endOfDay();

        if ($selectedEnd->lt($selectedStart)) {
            [$selectedStart, $selectedEnd] = [$selectedEnd->copy()->startOfDay(), $selectedStart->copy()->endOfDay()];
        }

        $maxEnd = $selectedStart->copy()
            ->addMonthsNoOverflow(self::MAX_RANGE_MONTHS)
            ->subDay()
            ->endOfDay();

        if ($selectedEnd->gt($maxEnd)) {
            $selectedEnd = $maxEnd;
        }

        return [$selectedStart, $selectedEnd];
    }

    /** @return array{start: string, end: string} */
    public function normalizeRange(?string $start, ?string $end): array
    {
        [$normalizedStart, $normalizedEnd] = $this->normalizeSelectedRange($start, $end);

        return [
            'start' => $normalizedStart->format('Y-m-d'),
            'end' => $normalizedEnd->format('Y-m-d'),
        ];
    }

    public static function normalizePreset(?string $preset): ?string
    {
        if (! $preset) {
            return null;
        }

        if ($preset === self::PRESET_CUSTOM) {
            return self::PRESET_CUSTOM;
        }

        return in_array($preset, [
            self::PRESET_THIS_WEEK,
            self::PRESET_THIS_MONTH,
            self::PRESET_LAST_MONTH,
            self::PRESET_LAST_7_DAYS,
            self::PRESET_LAST_30_DAYS,
            self::PRESET_THIS_QUARTER,
            self::PRESET_LAST_QUARTER,
            self::PRESET_NEXT_QUARTER,
            self::PRESET_NEXT_MONTH,
            self::PRESET_NEXT_7_DAYS,
            self::PRESET_NEXT_30_DAYS,
            self::PRESET_NEXT_3_MONTHS,
            self::PRESET_NEXT_6_MONTHS,
        ], true) ? $preset : null;
    }

    public static function isConcretePreset(?string $preset): bool
    {
        return in_array($preset, [
            self::PRESET_THIS_WEEK,
            self::PRESET_THIS_MONTH,
            self::PRESET_LAST_MONTH,
            self::PRESET_LAST_7_DAYS,
            self::PRESET_LAST_30_DAYS,
            self::PRESET_THIS_QUARTER,
            self::PRESET_LAST_QUARTER,
            self::PRESET_NEXT_QUARTER,
            self::PRESET_NEXT_MONTH,
            self::PRESET_NEXT_7_DAYS,
            self::PRESET_NEXT_30_DAYS,
            self::PRESET_NEXT_3_MONTHS,
            self::PRESET_NEXT_6_MONTHS,
        ], true);
    }

    /** @return array{Carbon, Carbon} */
    public function presetRange(string $preset): array
    {
        $normalizedPreset = self::normalizePreset($preset) ?? self::PRESET_NEXT_3_MONTHS;
        [$start, $end] = DateRangePreset::from($normalizedPreset)->dates();

        return [$start->copy()->startOfDay(), $end->copy()->endOfDay()];
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
        [$start, $end] = self::isConcretePreset($settings->preset)
            ? $this->presetRange($settings->preset)
            : $this->normalizeSelectedRange($settings->start, $settings->end);

        return $start->format('d.m.Y').' – '.$end->format('d.m.Y');
    }
}
