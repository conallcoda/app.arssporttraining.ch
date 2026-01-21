<?php

namespace App\Livewire\Calculator;

use App\Livewire\Concerns\ManagesDatabaseList;
use App\Models\Training\ExercisePlan\ExerciseData;
use Livewire\Component;

class ExerciseDatabase extends Component
{
    use ManagesDatabaseList;

    public array $exercises = [];

    public string $selectedGroup = 'Strength 1';

    public array $groups = ['Strength 1', 'Strength 2'];

    public bool $showAddGroupModal = false;

    public string $newGroupName = '';

    public string $newName = '';

    public float $newModifier = 100.0;

    public function mount(): void
    {
        $this->exercises = [
            ExerciseData::back_squat(),
            ExerciseData::front_squat(),
            ExerciseData::deadlift(),
            ExerciseData::press(),
        ];

        $this->emitExercises();
        $this->dispatch('groups-updated', groups: $this->groups);
    }

    public function selectGroup(string $group): void
    {
        $this->selectedGroup = $group;
    }

    public function toggleAddGroupModal(): void
    {
        $this->showAddGroupModal = ! $this->showAddGroupModal;
        if (! $this->showAddGroupModal) {
            $this->newGroupName = '';
        }
    }

    public function addGroup(): void
    {
        $this->validate([
            'newGroupName' => 'required|string|min:1|max:50',
        ]);

        if (! in_array($this->newGroupName, $this->groups)) {
            $this->groups[] = $this->newGroupName;
            $this->selectedGroup = $this->newGroupName;
            $this->dispatch('groups-updated', groups: $this->groups);
        }

        $this->newGroupName = '';
        $this->showAddGroupModal = false;
    }

    public function getFilteredExercisesProperty(): array
    {
        return array_filter(
            $this->exercises,
            fn ($ex) => $ex->group === $this->selectedGroup
        );
    }

    public function updateExerciseName(int $exerciseId, string $value): void
    {
        foreach ($this->exercises as $index => $exercise) {
            if ($exercise->id === $exerciseId) {
                $this->exercises[$index] = new ExerciseData(
                    id: $exercise->id,
                    name: $value,
                    modifier: $exercise->modifier,
                    group: $exercise->group,
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
                    group: $exercise->group,
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
            group: $this->selectedGroup,
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

    public function moveExerciseUp(int $exerciseId): void
    {
        $groupExercises = [];
        $groupIndices = [];

        foreach ($this->exercises as $index => $exercise) {
            if ($exercise->group === $this->selectedGroup) {
                $groupExercises[] = $exercise;
                $groupIndices[] = $index;
            }
        }

        foreach ($groupExercises as $i => $exercise) {
            if ($exercise->id === $exerciseId && $i > 0) {
                $currentIndex = $groupIndices[$i];
                $previousIndex = $groupIndices[$i - 1];

                $temp = $this->exercises[$currentIndex];
                $this->exercises[$currentIndex] = $this->exercises[$previousIndex];
                $this->exercises[$previousIndex] = $temp;

                $this->emitExercises();
                break;
            }
        }
    }

    public function moveExerciseDown(int $exerciseId): void
    {
        $groupExercises = [];
        $groupIndices = [];

        foreach ($this->exercises as $index => $exercise) {
            if ($exercise->group === $this->selectedGroup) {
                $groupExercises[] = $exercise;
                $groupIndices[] = $index;
            }
        }

        foreach ($groupExercises as $i => $exercise) {
            if ($exercise->id === $exerciseId && $i < count($groupExercises) - 1) {
                $currentIndex = $groupIndices[$i];
                $nextIndex = $groupIndices[$i + 1];

                $temp = $this->exercises[$currentIndex];
                $this->exercises[$currentIndex] = $this->exercises[$nextIndex];
                $this->exercises[$nextIndex] = $temp;

                $this->emitExercises();
                break;
            }
        }
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
        return md5($this->selectedGroup.json_encode(array_map(fn ($ex) => $ex->id, $this->exercises)));
    }

    public function getGroupsKey(): string
    {
        return md5(json_encode($this->groups));
    }

    public function render()
    {
        return view('livewire.calculator.exercise-database');
    }
}
