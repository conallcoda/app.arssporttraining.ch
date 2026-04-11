<?php

namespace App\Livewire\Athlete;

use App\Data\Athlete\ScheduledProgramData;
use App\Models\Training\TrainingProgramSlot;
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

    public ?int $readinessScore = null;

    public ?string $readinessLabel = null;

    public ?string $readinessColor = null;

    public function mount(string $date, string $viewMode = 'day', bool $showReadiness = true, ?int $readinessScore = null, ?string $readinessLabel = null, ?string $readinessColor = null): void
    {
        $this->date = $date;
        $this->viewMode = $viewMode;
        $this->showReadiness = $showReadiness;
        $this->readinessScore = $readinessScore;
        $this->readinessLabel = $readinessLabel;
        $this->readinessColor = $readinessColor;
    }

    #[On('readiness-updated')]
    public function onReadinessUpdated(int $score, ?string $label = null, ?string $color = null): void
    {
        $this->readinessScore = $score;
        $this->readinessLabel = $label;
        $this->readinessColor = $color;
    }

    public function render(): View
    {
        $requireReadinessForTrainingVisibility = (bool) config('athlete.require_readiness_for_training_visibility', true);
        $selectedDate = CarbonImmutable::parse($this->date);

        $rangeStart = $this->viewMode === 'week'
            ? $selectedDate->startOfWeek()
            : $selectedDate;

        $rangeEnd = $this->viewMode === 'week'
            ? $selectedDate->endOfWeek()
            : $selectedDate;

        $slots = TrainingProgramSlot::query()
            ->with(['trainingProgram.program.exerciseCategory', 'trainingProgram.program.exercises'])
            ->where('user_id', auth()->id())
            ->whereBetween('datetime', [
                $rangeStart->startOfDay(),
                $rangeEnd->endOfDay(),
            ])
            ->orderBy('datetime')
            ->get();

        $days = collect();

        if ($this->viewMode === 'week') {
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
                        'formattedDate' => $currentDate->locale(App::currentLocale())->translatedFormat('l, d.m.Y'),
                        'sessionCount' => $dayPrograms->count(),
                        'amPrograms' => $dayPrograms->filter(fn (ScheduledProgramData $program) => $program->period === 'am')->values(),
                        'pmPrograms' => $dayPrograms->filter(fn (ScheduledProgramData $program) => $program->period === 'pm')->values(),
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
                'formattedDate' => $selectedDate->locale(App::currentLocale())->translatedFormat('l, d.m.Y'),
                'sessionCount' => $programs->count(),
                'amPrograms' => $programs->filter(fn (ScheduledProgramData $program) => $program->period === 'am')->values(),
                'pmPrograms' => $programs->filter(fn (ScheduledProgramData $program) => $program->period === 'pm')->values(),
            ]);
        }

        $hasSchedule = $days->contains(fn (array $day) => $day['sessionCount'] > 0);

        return view('livewire.athlete.day-schedule', [
            'hasSchedule' => $hasSchedule,
            'days' => $days,
            'canShowTraining' => ! $this->showReadiness
                || ! $requireReadinessForTrainingVisibility
                || $this->readinessScore !== null,
            'emptyStateMessage' => $this->viewMode === 'week'
                ? 'No training programs for this week.'
                : 'No training programs for this day.',
        ]);
    }
}
