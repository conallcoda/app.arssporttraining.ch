<?php

namespace App\Livewire\Calculator;

use App\Livewire\Concerns\ManagesDatabaseList;
use App\Models\Training\ExercisePlan\ExerciseData;
use Livewire\Component;

class ExerciseDatabase extends Component
{
    use ManagesDatabaseList;

    public array $exercises = [];

    public string $newName = '';

    public float $newModifier = 100.0;

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

    public function updateExerciseName(int $exerciseId, string $value): void
    {
        foreach ($this->exercises as $index => $exercise) {
            if ($exercise->id === $exerciseId) {
                $this->exercises[$index] = new ExerciseData(
                    id: $exercise->id,
                    name: $value,
                    modifier: $exercise->modifier,
                );
                break;
            }
        }

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

    public function addExercise(): void
    {
        $this->validate([
            'newName' => 'required|string|min:1',
            'newModifier' => 'required|numeric|min:1',
        ]);

        $this->exercises[] = new ExerciseData(
            id: $this->getNextId(),
            name: $this->newName,
            modifier: $this->newModifier,
        );

        $this->resetAddForm();
        $this->showAddForm = false;
        $this->emitExercises();
    }

    public function removeExercise(int $exerciseId): void
    {
        $this->exercises = array_values(
            array_filter($this->exercises, fn ($ex) => $ex->id !== $exerciseId)
        );
        $this->emitExercises();
    }

    protected function emitExercises(): void
    {
        $this->dispatch('exercises-updated', exercises: array_map(
            fn ($ex) => $ex->toArray(),
            $this->exercises
        ));
    }

    protected function getItems(): array
    {
        return $this->exercises;
    }

    protected function resetAddForm(): void
    {
        $this->newName = '';
        $this->newModifier = 100.0;
    }

    public function getListKey(): string
    {
        return md5(json_encode(array_map(fn ($ex) => $ex->id, $this->exercises)));
    }

    public function render()
    {
        return view('livewire.calculator.exercise-database');
    }
}
