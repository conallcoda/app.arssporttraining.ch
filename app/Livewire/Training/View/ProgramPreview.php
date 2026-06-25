<?php

namespace App\Livewire\Training\View;

use App\Models\Exercise\ExerciseProgram;
use App\Support\Training\ProgramPreviewBuilder;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ProgramPreview extends Component
{
    public ExerciseProgram $exerciseProgram;

    public int $weeks = 1;

    public int $sessionsPerWeek = 1;

    public array $weekLabels = [];

    public array $weekSessions = [];

    public array $weekSessionDates = [];

    public ?int $userId = null;

    public ?int $planMeasuredReps = null;

    public ?float $planMeasuredWeight = null;

    public int|float|null $planTargetGoal = null;

    public ?int $planMaxHR = null;

    public ?int $planIatPercent = null;

    public ?string $selectedSessionKey = null;

    public string $activeSection = 'main';

    #[Computed]
    public function preview(): array
    {
        return app(ProgramPreviewBuilder::class)->build(
            exerciseProgram: $this->exerciseProgram,
            weeks: $this->weeks,
            sessionsPerWeek: $this->sessionsPerWeek,
            weekLabels: $this->weekLabels,
            weekSessions: $this->weekSessions,
            weekSessionDates: $this->weekSessionDates,
            userId: $this->userId,
            planMeasuredReps: $this->planMeasuredReps,
            planMeasuredWeight: $this->planMeasuredWeight,
            planTargetGoal: $this->planTargetGoal,
            planMaxHR: $this->planMaxHR,
            planIatPercent: $this->planIatPercent,
        );
    }

    #[Computed]
    public function days(): array
    {
        return $this->preview['days'] ?? [];
    }

    #[Computed]
    public function selectedSession(): ?array
    {
        $session = $this->preview['sessions'][$this->selectedSessionKey] ?? null;

        if ($session === null) {
            return null;
        }

        $availableSections = collect($session['sectionTabs'] ?? [])->pluck('key');

        if (! $availableSections->contains($this->activeSection)) {
            $this->activeSection = $availableSections->contains('main')
                ? 'main'
                : ($availableSections->first() ?? 'main');
        }

        return $session;
    }

    #[Computed]
    public function sectionInstructions(): ?string
    {
        return $this->exerciseProgram->config->sectionInstructions($this->activeSection);
    }

    public function openPreviewSession(string $sessionKey): void
    {
        $this->selectedSessionKey = $sessionKey;
        $this->activeSection = 'main';
    }

    public function backToPreviewList(): void
    {
        $this->selectedSessionKey = null;
    }

    public function render(): View
    {
        return view('livewire.training.view.program-preview');
    }
}
