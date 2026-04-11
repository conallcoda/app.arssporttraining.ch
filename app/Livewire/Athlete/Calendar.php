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

    public string $selectedDate = '';

    public function mount(string $calendarView = 'day', ?string $date = null): void
    {
        $this->calendarView = $calendarView;
        $this->dashboardDate = AthleteDashboardDate::todayDateString();
        $this->selectedDate = $date ?: $this->dashboardDate;

        if ($this->calendarView === 'week') {
            $canonicalWeekStart = CarbonImmutable::parse($this->selectedDate)->startOfWeek()->format('Y-m-d');

            if ($this->selectedDate !== $canonicalWeekStart) {
                $this->redirectRoute('athlete.dashboard.calendar.week', ['date' => $canonicalWeekStart], navigate: true);

                return;
            }
        }
    }

    public function updatedSelectedDate(string $value): void
    {
        $route = $this->calendarView === 'week'
            ? 'athlete.dashboard.calendar.week'
            : 'athlete.dashboard.calendar';

        $this->redirect(route($route, ['date' => $value]), navigate: true);
    }

    #[Computed]
    public function selectedDateValue(): string
    {
        return $this->selectedDate !== '' ? $this->selectedDate : $this->dashboardDate;
    }

    #[Computed]
    public function selectedWeekStart(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->selectedDateValue)->startOfWeek();
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
        return route('athlete.dashboard.calendar', ['date' => CarbonImmutable::parse($this->selectedDateValue)->subDay()->format('Y-m-d')]);
    }

    #[Computed]
    public function nextDayUrl(): string
    {
        return route('athlete.dashboard.calendar', ['date' => CarbonImmutable::parse($this->selectedDateValue)->addDay()->format('Y-m-d')]);
    }

    public function render(): View
    {
        return view('livewire.athlete.calendar')
            ->layout('components.layouts.athlete', ['title' => 'Calendar']);
    }
}
