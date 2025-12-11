<?php

namespace App\Livewire\Calculator;

use App\Models\Training\ExercisePlan\AthleteData;
use App\Models\Training\ExercisePlan\AthleteExerciseBlockHistory;
use App\Models\Training\ExercisePlan\AthleteExerciseConfig;
use App\Models\Training\ExercisePlan\ExerciseData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class AthleteTrainingPlan extends Component
{
    #[Reactive]
    public array $athletes = [];

    #[Reactive]
    public array $exercises = [];

    #[Reactive]
    public ?AthleteExerciseConfig $config = null;

    public ?int $selectedAthleteId = null;

    public bool $showBreakdownModal = false;

    public ?int $breakdownExerciseId = null;

    #[Computed]
    public function selectedAthlete(): ?AthleteData
    {
        if (empty($this->athletes)) {
            return null;
        }

        if ($this->selectedAthleteId === null) {
            return $this->athletes[0] ?? null;
        }

        return collect($this->athletes)->first(fn ($a) => $a->id === $this->selectedAthleteId) ?? $this->athletes[0];
    }

    #[Computed]
    public function breakdownExercise(): ?ExerciseData
    {
        if ($this->breakdownExerciseId === null || empty($this->exercises)) {
            return null;
        }

        return collect($this->exercises)->first(fn ($e) => $e->id === $this->breakdownExerciseId);
    }

    #[Computed]
    public function breakdownHistory(): ?AthleteExerciseBlockHistory
    {
        $athlete = $this->selectedAthlete;
        $exercise = $this->breakdownExercise;

        if (! $athlete || ! $exercise || ! $this->config) {
            return null;
        }

        $exerciseConfig = AthleteExerciseConfig::fromAthleteExerciseAndTarget(
            athlete: $athlete,
            exercise: $exercise,
            target: $this->config->target,
            strategy: $this->config->strategy,
            strategyConfig: $this->config->strategyConfig,
            initialRulesConfig: $this->config->initialRulesConfig,
            actionRulesConfig: $this->config->actionRulesConfig,
        );

        return AthleteExerciseBlockHistory::example($exerciseConfig);
    }

    #[Computed]
    public function exerciseBlocks(): array
    {
        $athlete = $this->selectedAthlete;
        if (! $athlete || empty($this->exercises) || ! $this->config) {
            return [];
        }

        $blocks = [];
        foreach ($this->exercises as $exercise) {
            $exerciseConfig = AthleteExerciseConfig::fromAthleteExerciseAndTarget(
                athlete: $athlete,
                exercise: $exercise,
                target: $this->config->target,
                strategy: $this->config->strategy,
                strategyConfig: $this->config->strategyConfig,
                initialRulesConfig: $this->config->initialRulesConfig,
                actionRulesConfig: $this->config->actionRulesConfig,
            );

            $history = AthleteExerciseBlockHistory::example($exerciseConfig);

            $blocks[] = [
                'exercise' => $exercise,
                'block' => $history->current(),
            ];
        }

        return $blocks;
    }

    public function selectAthlete(int $athleteId): void
    {
        $this->selectedAthleteId = $athleteId;
    }

    public function openBreakdown(int $exerciseId): void
    {
        $this->breakdownExerciseId = $exerciseId;
        $this->showBreakdownModal = true;
    }

    public function closeBreakdown(): void
    {
        $this->showBreakdownModal = false;
        $this->breakdownExerciseId = null;
    }

    public function render()
    {
        return view('livewire.calculator.athlete-training-plan');
    }
}
