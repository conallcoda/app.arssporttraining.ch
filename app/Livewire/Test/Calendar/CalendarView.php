<?php

namespace App\Livewire\Test\Calendar;

use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.athlete')]
#[Title('Calendar')]
class CalendarView extends Component
{
    public string $currentView = 'dayGridMonth';

    /** @return array<int, array{id: string, title: string, start: string, end: string, extendedProps: array{slots: array}}> */
    #[Computed]
    public function events(): array
    {
        $monday = Carbon::now()->startOfWeek();

        $schedule = [
            0 => [
                0 => [
                    ['name' => 'Upper Body Strength', 'color' => '#3b82f6'],
                    ['name' => 'Core Work', 'color' => '#22c55e'],
                ],
                1 => [
                    ['name' => 'Yoga Recovery', 'color' => '#a855f7'],
                ],
            ],
            1 => [
                0 => [
                    ['name' => 'Sprint Intervals', 'color' => '#ef4444'],
                ],
            ],
            2 => [
                0 => [
                    ['name' => 'Lower Body Power', 'color' => '#3b82f6'],
                ],
                1 => [
                    ['name' => 'Mobility Session', 'color' => '#f97316'],
                ],
            ],
            3 => [
                0 => [
                    ['name' => 'Cardio HIIT', 'color' => '#ef4444'],
                    ['name' => 'Abs Circuit', 'color' => '#22c55e'],
                ],
            ],
            4 => [
                0 => [
                    ['name' => 'Full Body Strength', 'color' => '#3b82f6'],
                ],
                1 => [
                    ['name' => 'Active Recovery', 'color' => '#a855f7'],
                ],
            ],
        ];

        $events = [];

        foreach ($schedule as $dayOffset => $slots) {
            $date = $monday->copy()->addDays($dayOffset);

            $events[] = [
                'id' => (string) ($dayOffset + 1),
                'title' => '',
                'start' => $date->format('Y-m-d'),
                'end' => $date->format('Y-m-d'),
                'allDay' => true,
                'backgroundColor' => 'transparent',
                'extendedProps' => [
                    'slots' => [
                        'am' => $slots[0] ?? [],
                        'pm' => $slots[1] ?? [],
                    ],
                ],
            ];
        }

        return $events;
    }

    public function onEventClick(string $eventId): void
    {
        //
    }

    public function render()
    {
        return view('livewire.test.calendar.calendar-view');
    }
}
