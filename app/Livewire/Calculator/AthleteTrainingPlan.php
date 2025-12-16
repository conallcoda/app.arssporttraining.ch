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

    public array $exerciseOverrides = [];

    public string $overrideDisplayMode = 'radios';

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

        $exerciseConfig = $this->buildExerciseConfig($athlete, $exercise);

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
            $exerciseConfig = $this->buildExerciseConfig($athlete, $exercise);
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

    public function getExerciseTarget(int $exerciseId): float
    {
        return (float) ($this->exerciseOverrides[$exerciseId]['target'] ?? $this->config?->target ?? 10);
    }

    public function getExerciseStartingReps(int $exerciseId): int
    {
        return (int) ($this->exerciseOverrides[$exerciseId]['startingReps']
            ?? $this->config?->strategyConfig['set_paired_reps']['startingReps']
            ?? 12);
    }

    public function getExerciseSets(int $exerciseId): int
    {
        return (int) ($this->exerciseOverrides[$exerciseId]['sets']
            ?? $this->config?->strategyConfig['create_empty_block']['sets']
            ?? 4);
    }

    public function updateExerciseOverride(int $exerciseId, string $field, float $value): void
    {
        $default = match ($field) {
            'target' => $this->config?->target ?? 10,
            'startingReps' => $this->config?->strategyConfig['set_paired_reps']['startingReps'] ?? 12,
            'sets' => $this->config?->strategyConfig['create_empty_block']['sets'] ?? 4,
            default => null,
        };

        if ($default !== null && (float) $value === (float) $default) {
            if (isset($this->exerciseOverrides[$exerciseId][$field])) {
                unset($this->exerciseOverrides[$exerciseId][$field]);
                if (empty($this->exerciseOverrides[$exerciseId])) {
                    unset($this->exerciseOverrides[$exerciseId]);
                }
            }

            return;
        }

        if (! isset($this->exerciseOverrides[$exerciseId])) {
            $this->exerciseOverrides[$exerciseId] = [];
        }
        $this->exerciseOverrides[$exerciseId][$field] = $value;
    }

    public function clearExerciseOverride(int $exerciseId, string $field): void
    {
        if (isset($this->exerciseOverrides[$exerciseId][$field])) {
            unset($this->exerciseOverrides[$exerciseId][$field]);
            if (empty($this->exerciseOverrides[$exerciseId])) {
                unset($this->exerciseOverrides[$exerciseId]);
            }
        }
    }

    protected function buildExerciseConfig(AthleteData $athlete, ExerciseData $exercise): AthleteExerciseConfig
    {
        $overrides = $this->exerciseOverrides[$exercise->id] ?? [];

        $target = $overrides['target'] ?? $this->config->target;

        $strategyConfig = $this->config->strategyConfig;
        if (isset($overrides['startingReps'])) {
            $strategyConfig['set_paired_reps']['startingReps'] = (int) $overrides['startingReps'];
        }
        if (isset($overrides['sets'])) {
            $strategyConfig['create_empty_block']['sets'] = (int) $overrides['sets'];
        }

        return AthleteExerciseConfig::fromAthleteExerciseAndTarget(
            athlete: $athlete,
            exercise: $exercise,
            target: $target,
            strategy: $this->config->strategy,
            strategyConfig: $strategyConfig,
            initialRulesConfig: $this->config->initialRulesConfig,
            actionRulesConfig: $this->config->actionRulesConfig,
        );
    }

    public function render()
    {
        return view('livewire.calculator.athlete-training-plan');
    }
}
