<?php

namespace App\Livewire\Calculator;

use App\Models\Training\ExercisePlan\AthleteExerciseBlockHistory;
use App\Models\Training\ExercisePlan\AthleteExerciseConfig;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class ExercisePlanBreakdown extends Component
{
    #[Reactive]
    public ?AthleteExerciseConfig $config = null;

    #[Reactive]
    public string $strategy = 'fixed_decrement';

    #[Computed]
    public function history(): ?AthleteExerciseBlockHistory
    {
        if (! $this->config) {
            return null;
        }

        return AthleteExerciseBlockHistory::example($this->config);
    }

    public function render()
    {
        return view('livewire.calculator.exercise-plan-breakdown');
    }
}
