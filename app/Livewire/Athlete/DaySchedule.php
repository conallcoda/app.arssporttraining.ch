<?php

namespace App\Livewire\Athlete;

use App\Data\Athlete\ScheduledProgramData;
use App\Models\Training\TrainingProgramSlot;
use App\Support\AthleteDashboardDate;
use App\Support\Readiness\ReadinessMetricService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class DaySchedule extends Component
{
    public string $date;

    public string $viewMode = 'day';

    public bool $showReadiness = true;

    public string $scheduleFilter = 'today';

    public ?float $readinessScore = null;

    public ?string $readinessLabel = null;

    public ?string $readinessColor = null;

    public function mount(string $date, string $viewMode = 'day', bool $showReadiness = true, ?float $readinessScore = null, ?string $readinessLabel = null, ?string $readinessColor = null, string $scheduleFilter = 'today'): void
    {
        $this->date = $date;
        $this->viewMode = $viewMode;
        $this->showReadiness = $showReadiness
            && $this->viewMode === 'day'
            && AthleteDashboardDate::canSubmitReadinessForDate($this->date);
        $this->readinessScore = $readinessScore;
        $this->readinessLabel = $readinessLabel;
        $this->readinessColor = $readinessColor;
        $this->scheduleFilter = in_array($scheduleFilter, ['today', 'unrecorded'], true) ? $scheduleFilter : 'today';

        if ($this->showReadiness && $this->readinessScore === null) {
            $presentation = app(ReadinessMetricService::class)->presentationForDate((int) auth()->id(), $this->date);
            $this->readinessScore = $presentation['score'];
            $this->readinessLabel = $presentation['label'];
            $this->readinessColor = $presentation['color'];
        }
    }

    #[On('readiness-updated')]
    public function onReadinessUpdated(float $score, ?string $label = null, ?string $color = null, ?string $date = null): void
    {
        if ($date !== null && $date !== $this->date) {
            return;
        }

        $this->readinessScore = $score;
        $this->readinessLabel = $label;
        $this->readinessColor = $color;
    }

    public function render(): View
    {
        $requireReadinessForTrainingVisibility = (bool) config('athlete.require_readiness_for_training_visibility', true);
        $selectedDate = CarbonImmutable::parse($this->date);
        $dashboardToday = AthleteDashboardDate::today();

        $rangeStart = $this->viewMode === 'week'
            ? $selectedDate->startOfWeek()
            : $selectedDate;

        $rangeEnd = $this->viewMode === 'week'
            ? $selectedDate->endOfWeek()
            : $selectedDate;

        $slots = TrainingProgramSlot::query()
            ->with(['trainingProgram.program.exerciseCategory', 'trainingProgram.program.exercises', 'exercises'])
            ->where('user_id', auth()->id())
            ->when(
                $this->scheduleFilter === 'unrecorded',
                fn ($query) => $query
                    ->where('datetime', '<', $dashboardToday->startOfDay())
                    ->orderByDesc('datetime'),
                fn ($query) => $query
                    ->whereBetween('datetime', [
                        $rangeStart->startOfDay(),
                        $rangeEnd->endOfDay(),
                    ])
                    ->orderBy('datetime')
            )
            ->get();

        $days = collect();

        if ($this->scheduleFilter === 'unrecorded') {
            $days = $slots
                ->map(fn (TrainingProgramSlot $slot) => [
                    'date' => $slot->datetime->format('Y-m-d'),
                    'datetime' => $slot->datetime,
                    'program' => ScheduledProgramData::fromSlot($slot),
                ])
                ->filter(fn (array $row) => $row['program']->exerciseCount > 0)
                ->filter(fn (array $row) => $row['program']->recordedExerciseCount < $row['program']->totalExerciseCount)
                ->groupBy('date')
                ->map(fn ($rows, string $dateKey) => [
                    'date' => $dateKey,
                    'isFuture' => AthleteDashboardDate::isFutureDate($dateKey),
                    'formattedDate' => CarbonImmutable::parse($dateKey)->locale(App::currentLocale())->translatedFormat('l, d.m.Y'),
                    'sessionCount' => $rows->count(),
                    'programs' => $rows->sortByDesc(fn (array $row) => $row['datetime'])->pluck('program')->values(),
                ])
                ->sortByDesc('date')
                ->values();
        } elseif ($this->viewMode === 'week') {
            $currentDate = $rangeStart;

            while ($currentDate->lte($rangeEnd)) {
                $dateKey = $currentDate->format('Y-m-d');

                $dayPrograms = $slots
                    ->filter(fn (TrainingProgramSlot $slot) => $slot->datetime->format('Y-m-d') === $dateKey)
                    ->map(fn (TrainingProgramSlot $slot) => ScheduledProgramData::fromSlot($slot))
                    ->filter(fn (ScheduledProgramData $program) => $program->exerciseCount > 0)
                    ->values();

                if ($dayPrograms->isNotEmpty()) {
                    $days->push([
                        'date' => $dateKey,
                        'isFuture' => AthleteDashboardDate::isFutureDate($dateKey),
                        'formattedDate' => $currentDate->locale(App::currentLocale())->translatedFormat('l, d.m.Y'),
                        'sessionCount' => $dayPrograms->count(),
                        'programs' => $dayPrograms,
                    ]);
                }

                $currentDate = $currentDate->addDay();
            }
        } else {
            $programs = $slots
                ->map(fn (TrainingProgramSlot $slot) => ScheduledProgramData::fromSlot($slot))
                ->filter(fn (ScheduledProgramData $program) => $program->exerciseCount > 0)
                ->values();

            $days->push([
                'date' => $selectedDate->format('Y-m-d'),
                'isFuture' => AthleteDashboardDate::isFutureDate($selectedDate),
                'formattedDate' => $selectedDate->locale(App::currentLocale())->translatedFormat('l, d.m.Y'),
                'sessionCount' => $programs->count(),
                'programs' => $programs,
            ]);
        }

        $hasSchedule = $days->contains(fn (array $day) => $day['sessionCount'] > 0);

        return view('livewire.athlete.day-schedule', [
            'hasSchedule' => $hasSchedule,
            'days' => $days,
            'canShowTraining' => ! $this->showReadiness
                || ! $requireReadinessForTrainingVisibility
                || $this->readinessScore !== null,
            'emptyStateMessage' => $this->scheduleFilter === 'unrecorded'
                ? 'No unrecorded training sessions before today.'
                : ($this->viewMode === 'week'
                    ? 'No training programs for this week.'
                    : 'No training programs for this day.'),
        ]);
    }
}
