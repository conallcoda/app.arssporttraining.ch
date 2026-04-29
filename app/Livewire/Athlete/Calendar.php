<?php

namespace App\Livewire\Athlete;

use App\Support\AthleteDashboardDate;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Calendar extends Component
{
    public string $calendarView = 'day';

    public string $dashboardDate;

    public string $selectedDayDate = '';

    public string $selectedWeekDate = '';

    public function mount(string $calendarView = 'day', ?string $date = null): void
    {
        $this->calendarView = in_array($calendarView, ['day', 'week', 'unrecorded'], true)
            ? $calendarView
            : 'day';
        $this->dashboardDate = AthleteDashboardDate::todayDateString();
        $this->selectedDayDate = session('athlete.calendar.day_date', $this->dashboardDate);
        $this->selectedWeekDate = session(
            'athlete.calendar.week_date',
            CarbonImmutable::parse($this->dashboardDate)->startOfWeek()->format('Y-m-d'),
        );

        if ($this->calendarView === 'day') {
            $this->selectedDayDate = $date
                ? CarbonImmutable::parse($date)->format('Y-m-d')
                : $this->selectedDayDate;

            session(['athlete.calendar.day_date' => $this->selectedDayDate]);

            return;
        }

        if ($this->calendarView === 'week') {
            $requestedWeekDate = $date ?: $this->selectedWeekDate;
            $canonicalWeekStart = CarbonImmutable::parse($requestedWeekDate)->startOfWeek()->format('Y-m-d');

            if ($date !== null && $date !== $canonicalWeekStart) {
                $this->redirectRoute('athlete.dashboard.calendar.week', ['date' => $canonicalWeekStart], navigate: true);

                return;
            }

            $this->selectedWeekDate = $canonicalWeekStart;
            session(['athlete.calendar.week_date' => $this->selectedWeekDate]);
        }
    }

    public function updatedSelectedDayDate(string $value): void
    {
        $resolvedDate = CarbonImmutable::parse($value)->format('Y-m-d');
        $this->selectedDayDate = $resolvedDate;
        session(['athlete.calendar.day_date' => $resolvedDate]);

        $this->redirect(route('athlete.dashboard.calendar', ['date' => $resolvedDate]), navigate: true);
    }

    public function updatedSelectedWeekDate(string $value): void
    {
        $canonicalWeekStart = CarbonImmutable::parse($value)->startOfWeek()->format('Y-m-d');
        $this->selectedWeekDate = $canonicalWeekStart;
        session(['athlete.calendar.week_date' => $canonicalWeekStart]);

        $this->redirect(route('athlete.dashboard.calendar.week', ['date' => $canonicalWeekStart]), navigate: true);
    }

    #[Computed]
    public function selectedDayDateValue(): string
    {
        return $this->selectedDayDate !== '' ? $this->selectedDayDate : $this->dashboardDate;
    }

    #[Computed]
    public function selectedWeekDateValue(): string
    {
        return $this->selectedWeekDate !== ''
            ? $this->selectedWeekDate
            : CarbonImmutable::parse($this->dashboardDate)->startOfWeek()->format('Y-m-d');
    }

    #[Computed]
    public function selectedWeekStart(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->selectedWeekDateValue)->startOfWeek();
    }

    #[Computed]
    public function previousWeekUrl(): string
    {
        return route('athlete.dashboard.calendar.week', ['date' => $this->selectedWeekStart->subWeek()->format('Y-m-d')]);
    }

    #[Computed]
    public function nextWeekUrl(): string
    {
        return route('athlete.dashboard.calendar.week', ['date' => $this->selectedWeekStart->addWeek()->format('Y-m-d')]);
    }

    #[Computed]
    public function previousDayUrl(): string
    {
        return route('athlete.dashboard.calendar', ['date' => CarbonImmutable::parse($this->selectedDayDateValue)->subDay()->format('Y-m-d')]);
    }

    #[Computed]
    public function nextDayUrl(): string
    {
        return route('athlete.dashboard.calendar', ['date' => CarbonImmutable::parse($this->selectedDayDateValue)->addDay()->format('Y-m-d')]);
    }

    public function render(): View
    {
        return view('livewire.athlete.calendar')
            ->layout('components.layouts.athlete', ['title' => 'Calendar']);
    }
}
