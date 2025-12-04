<?php

namespace App\Livewire;

use App\Models\Training\ExercisePlan\AthleteData;
use App\Models\Training\ExercisePlan\AthleteExerciseConfig;
use App\Models\Training\ExercisePlan\ExerciseBlockManager;
use App\Models\Training\ExercisePlan\ExerciseData;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ProgressionExample extends Component
{
    public array $athletes = [];

    public AthleteData $athlete;

    public array $exercises = [];

    public ExerciseData $exercise;

    public ExerciseBlockManager $manager;

    public AthleteExerciseConfig $config;

    public int $selectedAthleteId = 1;

    public int $selectedExerciseId = 2;

    public float $targetGoal = 10;

    public int $startingReps = 14;

    public string $selectedStrategy = 'fixed_decrement';

    public function mount(): void
    {
        $this->athletes[] = AthleteData::example();
        $this->athletes[] = AthleteData::strong_doe();

        $this->athlete = $this->athletes[0];
        $this->exercises = [
            ExerciseData::back_squat(),
            ExerciseData::front_squat(),
        ];

        $this->exercise = $this->exercises[1];
        $this->updateConfig();
        $this->manager = ExerciseBlockManager::example($this->config);
    }

    public function updatedSelectedAthleteId(): void
    {
        $this->athlete = collect($this->athletes)->first(fn ($a) => $a->id === $this->selectedAthleteId) ?? $this->athletes[0];
        $this->updateConfig();
    }

    public function updatedSelectedExerciseId(): void
    {
        $this->exercise = collect($this->exercises)->first(fn ($e) => $e->id === $this->selectedExerciseId) ?? $this->exercises[0];
        $this->updateConfig();
    }

    public function updatedTargetGoal(): void
    {
        $this->updateConfig();
    }

    public function updatedStartingReps(): void
    {
        $this->updateConfig();
    }

    public function updatedSelectedStrategy(): void
    {
        $this->updateConfig();
    }

    public function getStrategies(): array
    {
        return ExerciseBlockManager::strategies();
    }

    private function updateConfig(): void
    {
        $this->config = AthleteExerciseConfig::fromAthleteExerciseAndTarget(
            athlete: $this->athlete,
            exercise: $this->exercise,
            target: $this->targetGoal,
            startingReps: $this->startingReps,
            strategy: $this->selectedStrategy,
        );
        $this->manager = ExerciseBlockManager::example($this->config);
    }

    public function render()
    {
        return view('livewire.progression-example');
    }
}
