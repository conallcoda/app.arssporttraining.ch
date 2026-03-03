<?php

namespace App\Form\Fields\Training\Calendar;

use Carbon\Carbon;
use Coda\Cms\Form\Fields\Date;
use Coda\Cms\Form\Fields\Preset;

class CalendarPreset extends Preset
{
    public static function month(string $name = 'date'): static
    {
        $field = new static($name);

        $now = Carbon::now();
        $presets = [];

        for ($i = -6; $i <= 6; $i++) {
            $month = $now->copy()->addMonths($i);
            $presets[] = [
                'value' => $month->startOfMonth()->format('Y-m-d'),
                'label' => $month->format('M y'),
            ];
        }

        $field->presets = $presets;
        $field->otherField = Date::make($name);

        return $field;
    }

    public static function week(string $name = 'date'): static
    {
        $field = new static($name);

        $weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);
        $now = Carbon::now();
        $presets = [];

        for ($i = -6; $i <= 6; $i++) {
            $weekStart = $now->copy()->startOfWeek($weekStartsOn)->addWeeks($i);
            $presets[] = [
                'value' => $weekStart->format('Y-m-d'),
                'label' => 'W'.$weekStart->isoWeek(),
            ];
        }

        $field->presets = $presets;
        $field->otherField = Date::make($name);

        return $field;
    }

    public static function day(string $name = 'date'): static
    {
        $field = new static($name);

        $now = Carbon::today();
        $presets = [];

        for ($i = -7; $i <= 7; $i++) {
            $day = $now->copy()->addDays($i);
            $suffix = match ($i) {
                -1 => ' (Yesterday)',
                0 => ' (Today)',
                1 => ' (Tomorrow)',
                default => '',
            };

            $presets[] = [
                'value' => $day->format('Y-m-d'),
                'label' => $day->format('D d').$suffix,
            ];
        }

        $field->presets = $presets;
        $field->otherField = Date::make($name);

        return $field;
    }

    public static function range(): static
    {
        $weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);
        $weekEndsOn = ($weekStartsOn + 6) % 7;

        $field = new static('_range_presets');

        $now = Carbon::now();
        $presets = [];

        for ($i = -3; $i <= 3; $i++) {
            $originalStart = $now->copy()->addMonths($i)->startOfMonth();
            $originalEnd = $now->copy()->addMonths($i + 1)->endOfMonth();
            $start = $originalStart->copy()->startOfWeek($weekStartsOn);
            $end = $originalEnd->copy()->endOfWeek($weekEndsOn);

            $presets[] = [
                'label' => $originalStart->format('M').' – '.$originalEnd->format('M y'),
                'values' => [
                    'start' => $start->format('Y-m-d'),
                    'end' => $end->format('Y-m-d'),
                ],
            ];
        }

        $field->presets = $presets;
        $field->otherFields = [
            Date::make('start')->label('Start'),
            Date::make('end')->label('End'),
        ];

        return $field;
    }
}
