<?php

namespace App\Livewire\Calculator;

use App\Models\Training\ExercisePlan\ExerciseData;
use Livewire\Component;

class ExerciseDatabase extends Component
{
    public array $exercises = [];

    public function mount(): void
    {
        $this->exercises = [
            ExerciseData::back_squat(),
            ExerciseData::front_squat(),
            //  ExerciseData::from(['id' => 3, 'name' => 'Deadlift (Wide)', 'modifier' => 85.0]),
            // ExerciseData::from(['id' => 4, 'name' => 'Deadlift (Narrow)', 'modifier' => 105.0]),
            // ExerciseData::from(['id' => 5, 'name' => 'Row', 'modifier' => 100.0]),
        ];

        $this->emitExercises();
    }

    public function updateExerciseModifier(int $exerciseId, float $value): void
    {
        foreach ($this->exercises as $index => $exercise) {
            if ($exercise->id === $exerciseId) {
                $this->exercises[$index] = new ExerciseData(
                    id: $exercise->id,
                    name: $exercise->name,
                    modifier: $value,
                );
                break;
            }
        }

        $this->emitExercises();
    }

    protected function emitExercises(): void
    {
        $this->dispatch('exercises-updated', exercises: $this->exercises);
    }

    public function render()
    {
        return view('livewire.calculator.exercise-database');
    }
}
