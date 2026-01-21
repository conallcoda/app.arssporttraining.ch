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
    public string $tab = 'exercises';

    public array $athletes = [];

    public array $exercises = [];

    public array $groups = ['Strength 1', 'Strength 2'];

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
            fn ($e) => ExerciseData::from($e),
            $exercises
        );
        $this->updateConfig();
    }

    #[On('groups-updated')]
    public function handleGroupsUpdated(array $groups): void
    {
        $this->groups = $groups;
    }

    #[On('athletes-updated')]
    public function handleAthletesUpdated(array $athletes): void
    {
        $this->athletes = array_map(
            fn ($a) => AthleteData::from($a),
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
            $this->config = null;

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

    public function getDataKey(): string
    {
        return md5(json_encode([
            'athletes' => count($this->athletes),
            'exercises' => count($this->exercises),
            'groups' => count($this->groups),
            'athleteIds' => array_map(fn ($a) => $a->id, $this->athletes),
            'exerciseIds' => array_map(fn ($e) => $e->id, $this->exercises),
        ]));
    }

    public function render()
    {
        return view('livewire.calculator');
    }
}
