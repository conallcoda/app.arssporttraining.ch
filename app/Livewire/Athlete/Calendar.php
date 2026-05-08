<?php

namespace App\Livewire\Athlete;

use App\Support\AthleteDashboardDate;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Calendar extends Component
{
    public string $dashboardMode = 'train';

    public string $dashboardDate;

    public string $selectedTrainDate = '';

    public string $selectedScheduleDate = '';

    public function mount(string $dashboardMode = 'train', ?string $date = null): void
    {
        $this->dashboardMode = in_array($dashboardMode, ['train', 'schedule', 'unrecorded'], true)
            ? $dashboardMode
            : 'train';
        $this->dashboardDate = AthleteDashboardDate::todayDateString();
        $this->selectedTrainDate = $this->dashboardDate;
        $this->selectedScheduleDate = CarbonImmutable::parse($this->dashboardDate)->startOfWeek()->format('Y-m-d');

        if ($this->dashboardMode === 'train') {
            $this->selectedTrainDate = $date
                ? CarbonImmutable::parse($date)->format('Y-m-d')
                : $this->selectedTrainDate;

            return;
        }

        if ($this->dashboardMode === 'schedule') {
            $requestedScheduleDate = $date ?: $this->selectedScheduleDate;
            $canonicalScheduleStart = CarbonImmutable::parse($requestedScheduleDate)->startOfWeek()->format('Y-m-d');

            if ($date !== null && $date !== $canonicalScheduleStart) {
                $this->redirectRoute('athlete.dashboard.schedule', ['date' => $canonicalScheduleStart], navigate: true);

                return;
            }

            $this->selectedScheduleDate = $canonicalScheduleStart;
        }
    }

    public function updatedSelectedTrainDate(string $value): void
    {
        $resolvedDate = CarbonImmutable::parse($value)->format('Y-m-d');
        $this->selectedTrainDate = $resolvedDate;

        $this->redirect(route('athlete.dashboard.train', ['date' => $resolvedDate]), navigate: true);
    }

    public function updatedSelectedScheduleDate(string $value): void
    {
        $canonicalScheduleStart = CarbonImmutable::parse($value)->startOfWeek()->format('Y-m-d');
        $this->selectedScheduleDate = $canonicalScheduleStart;

        $this->redirect(route('athlete.dashboard.schedule', ['date' => $canonicalScheduleStart]), navigate: true);
    }

    #[Computed]
    public function selectedTrainDateValue(): string
    {
        return $this->selectedTrainDate !== '' ? $this->selectedTrainDate : $this->dashboardDate;
    }

    #[Computed]
    public function selectedScheduleDateValue(): string
    {
        return $this->selectedScheduleDate !== ''
            ? $this->selectedScheduleDate
            : CarbonImmutable::parse($this->dashboardDate)->startOfWeek()->format('Y-m-d');
    }

    #[Computed]
    public function selectedScheduleStart(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->selectedScheduleDateValue)->startOfWeek();
    }

    #[Computed]
    public function previousScheduleUrl(): string
    {
        return route('athlete.dashboard.schedule', ['date' => $this->selectedScheduleStart->subWeek()->format('Y-m-d')]);
    }

    #[Computed]
    public function nextScheduleUrl(): string
    {
        return route('athlete.dashboard.schedule', ['date' => $this->selectedScheduleStart->addWeek()->format('Y-m-d')]);
    }

    #[Computed]
    public function previousTrainUrl(): string
    {
        return route('athlete.dashboard.train', ['date' => CarbonImmutable::parse($this->selectedTrainDateValue)->subDay()->format('Y-m-d')]);
    }

    #[Computed]
    public function nextTrainUrl(): string
    {
        return route('athlete.dashboard.train', ['date' => CarbonImmutable::parse($this->selectedTrainDateValue)->addDay()->format('Y-m-d')]);
    }

    public function render(): View
    {
        return view('livewire.athlete.calendar')
            ->layout('components.layouts.athlete', ['title' => 'Dashboard']);
    }
}
