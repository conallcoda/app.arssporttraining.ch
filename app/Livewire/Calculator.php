<?php

namespace App\Livewire;

use App\Models\Training\ExercisePlan\AthleteData;
use App\Models\Training\ExercisePlan\AthleteExerciseConfig;
use App\Models\Training\ExercisePlan\ExerciseData;
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

    public float $targetGoal = 0;

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
        $this->targetGoal = $configData['targetGoal'];
        $this->selectedStrategy = $configData['selectedStrategy'];
        $this->strategyConfig = $configData['strategyConfig'];
        $this->initialRulesConfig = $configData['initialRulesConfig'];
        $this->actionRulesConfig = $configData['actionRulesConfig'];
        $this->initialRulesEnabled = $configData['initialRulesEnabled'];
        $this->actionRulesEnabled = $configData['actionRulesEnabled'];

        $this->updateConfig();
    }

    protected function updateConfig(): void
    {
        $athlete = $this->athletes[0] ?? null;
        $exercise = $this->exercises[0] ?? null;

        if (! $athlete || ! $exercise) {
            return;
        }

        $this->config = AthleteExerciseConfig::fromAthleteExerciseAndTarget(
            athlete: $athlete,
            exercise: $exercise,
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
