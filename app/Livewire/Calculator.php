<?php

namespace App\Livewire;

use App\Models\Training\ExercisePlan\AthleteData;
use App\Models\Training\ExercisePlan\AthleteExerciseConfig;
use App\Models\Training\ExercisePlan\ExerciseData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Training Plan Calculator')]
class Calculator extends Component
{
    #[Url]
    public string $tab = 'calculator';

    public array $athletes = [];

    public array $exercises = [];

    public ?AthleteExerciseConfig $config = null;

    public int $selectedAthleteId = 1;

    public int $selectedExerciseId = 2;

    public float $targetGoal = 10;

    public string $selectedStrategy = 'fixed_decrement';

    public array $strategyConfig = [];

    public array $initialRulesConfig = [];

    public array $actionRulesConfig = [];

    public array $initialRulesEnabled = [];

    public array $actionRulesEnabled = [];

    #[On('exercises-updated')]
    public function handleExercisesUpdated(array $exercises): void
    {
        $this->exercises = array_map(
            fn ($e) => $e instanceof ExerciseData ? $e : ExerciseData::from($e),
            $exercises
        );
        $this->updateConfig();
    }

    #[On('athletes-updated')]
    public function handleAthletesUpdated(array $athletes): void
    {
        $this->athletes = array_map(
            fn ($a) => $a instanceof AthleteData ? $a : AthleteData::from($a),
            $athletes
        );
        $this->updateConfig();
    }

    #[On('config-changed')]
    public function handleConfigChanged(array $configData): void
    {
        $this->selectedAthleteId = $configData['selectedAthleteId'];
        $this->selectedExerciseId = $configData['selectedExerciseId'];
        $this->targetGoal = $configData['targetGoal'];
        $this->selectedStrategy = $configData['selectedStrategy'];
        $this->strategyConfig = $configData['strategyConfig'];
        $this->initialRulesConfig = $configData['initialRulesConfig'];
        $this->actionRulesConfig = $configData['actionRulesConfig'];
        $this->initialRulesEnabled = $configData['initialRulesEnabled'];
        $this->actionRulesEnabled = $configData['actionRulesEnabled'];

        $this->updateConfig();
    }

    #[Computed]
    public function athlete(): ?AthleteData
    {
        if (empty($this->athletes)) {
            return null;
        }

        return collect($this->athletes)->first(fn ($a) => $a->id === $this->selectedAthleteId) ?? $this->athletes[0];
    }

    #[Computed]
    public function exercise(): ?ExerciseData
    {
        if (empty($this->exercises)) {
            return null;
        }

        return collect($this->exercises)->first(fn ($e) => $e->id === $this->selectedExerciseId) ?? $this->exercises[0];
    }

    protected function updateConfig(): void
    {
        if (! $this->athlete || ! $this->exercise) {
            return;
        }

        $this->config = AthleteExerciseConfig::fromAthleteExerciseAndTarget(
            athlete: $this->athlete,
            exercise: $this->exercise,
            target: $this->targetGoal,
            strategy: $this->selectedStrategy,
            strategyConfig: $this->strategyConfig,
            initialRulesConfig: $this->initialRulesConfig,
            actionRulesConfig: $this->actionRulesConfig,
            initialRulesEnabled: $this->initialRulesEnabled,
            actionRulesEnabled: $this->actionRulesEnabled,
        );
    }

    public function render()
    {
        return view('livewire.calculator');
    }
}
