<?php

namespace App\Livewire\Test\Calendar;

use App\Support\Calendar\GridBuilder;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.athlete')]
#[Title('Calendar')]
class CalendarView extends Component
{
    public string $currentView = 'month';

    public string $currentDate = '';

    public function mount(): void
    {
        $this->currentDate = Carbon::today()->format('Y-m-d');
    }

    /** @return array{title: string, headers: list<string>, rows: list<list<array{date: string, day: int, isCurrentMonth: bool, isToday: bool}>>, cols: int} */
    #[Computed]
    public function grid(): array
    {
        return GridBuilder::build(Carbon::parse($this->currentDate), $this->currentView);
    }

    /** @return array<string, array{0: list<array{id: int, name: string, color: string}>, 1: list<array{id: int, name: string, color: string}>}> */
    #[Computed]
    public function schedule(): array
    {
        $monday = Carbon::parse($this->currentDate)->startOfWeek();

        $raw = [
            0 => [
                0 => [
                    ['id' => 1, 'name' => 'Upper Body Strength', 'color' => '#3b82f6'],
                    ['id' => 2, 'name' => 'Core Work', 'color' => '#22c55e'],
                ],
                1 => [
                    ['id' => 3, 'name' => 'Yoga Recovery', 'color' => '#a855f7'],
                ],
            ],
            1 => [
                0 => [
                    ['id' => 4, 'name' => 'Sprint Intervals', 'color' => '#ef4444'],
                ],
            ],
            2 => [
                0 => [
                    ['id' => 5, 'name' => 'Lower Body Power', 'color' => '#3b82f6'],
                ],
                1 => [
                    ['id' => 6, 'name' => 'Mobility Session', 'color' => '#f97316'],
                ],
            ],
            3 => [
                0 => [
                    ['id' => 7, 'name' => 'Cardio HIIT', 'color' => '#ef4444'],
                    ['id' => 8, 'name' => 'Abs Circuit', 'color' => '#22c55e'],
                ],
            ],
            4 => [
                0 => [
                    ['id' => 9, 'name' => 'Full Body Strength', 'color' => '#3b82f6'],
                ],
                1 => [
                    ['id' => 10, 'name' => 'Active Recovery', 'color' => '#a855f7'],
                ],
            ],
        ];

        $schedule = [];
        foreach ($raw as $dayOffset => $slots) {
            $dateKey = $monday->copy()->addDays($dayOffset)->format('Y-m-d');
            $schedule[$dateKey] = [
                0 => $slots[0] ?? [],
                1 => $slots[1] ?? [],
            ];
        }

        return $schedule;
    }

    public function navigate(string $direction): void
    {
        $date = Carbon::parse($this->currentDate);

        $result = match ($direction) {
            'prev' => match ($this->currentView) {
                'year' => $date->subYear(),
                'two-month' => $date->subMonth(),
                'month' => $date->subMonth(),
                'week' => $date->subWeek(),
                'day' => $date->subDay(),
            },
            'next' => match ($this->currentView) {
                'year' => $date->addYear(),
                'two-month' => $date->addMonth(),
                'month' => $date->addMonth(),
                'week' => $date->addWeek(),
                'day' => $date->addDay(),
            },
            'today' => Carbon::today(),
            default => $date,
        };

        $this->currentDate = $result->format('Y-m-d');
    }

    public function setView(string $view): void
    {
        $this->currentView = $view;
    }

    public function handleSort(int $programId, int $position, string $groupId): void
    {
        //
    }

    public function addProgram(int $programId, string $date, int $slot): void
    {
        //
    }

    public function onProgramClick(int $programId, string $date, int $slot): void
    {
        //
    }

    public function render()
    {
        return view('livewire.test.calendar.calendar-view');
    }
}
