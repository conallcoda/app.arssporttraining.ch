<?php

namespace App\Livewire\Athlete;

use App\Data\Athlete\ScheduledProgramData;
use App\Models\Training\TrainingProgramSlot;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class DaySchedule extends Component
{
    public string $date;

    public bool $showReadiness = true;

    public ?int $readinessScore = null;

    public ?string $readinessLabel = null;

    public ?string $readinessColor = null;

    public function mount(string $date, bool $showReadiness = true, ?int $readinessScore = null, ?string $readinessLabel = null, ?string $readinessColor = null): void
    {
        $this->date = $date;
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
        $slots = TrainingProgramSlot::query()
            ->with(['trainingProgram.program.exerciseCategory', 'trainingProgram.program.exercises'])
            ->where('user_id', auth()->id())
            ->whereDate('datetime', $this->date)
            ->orderBy('datetime')
            ->get();

        $amPrograms = $slots
            ->filter(fn (TrainingProgramSlot $slot) => $slot->datetime->format('H:i') < '12:00')
            ->map(fn (TrainingProgramSlot $slot) => ScheduledProgramData::fromSlot($slot))
            ->values();

        $pmPrograms = $slots
            ->filter(fn (TrainingProgramSlot $slot) => $slot->datetime->format('H:i') >= '12:00')
            ->map(fn (TrainingProgramSlot $slot) => ScheduledProgramData::fromSlot($slot))
            ->values();

        return view('livewire.athlete.day-schedule', [
            'hasSchedule' => $slots->isNotEmpty(),
            'amPrograms' => $amPrograms,
            'pmPrograms' => $pmPrograms,
        ]);
    }
}
