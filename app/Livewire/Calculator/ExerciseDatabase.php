<?php

namespace App\Livewire\Calculator;

use App\Models\Training\ExercisePlan\ExerciseData;
use Livewire\Component;

class ExerciseDatabase extends Component
{
    public array $exercises = [];

    public array $groups = ['Strength 1', 'Strength 2'];

    public bool $showAddGroupModal = false;

    public string $newGroupName = '';

    public array $showAddForm = [];

    public array $newName = [];

    public array $newModifier = [];

    public function mount(): void
    {
        $this->exercises = [
            ExerciseData::back_squat(),
            ExerciseData::front_squat(),
            ExerciseData::deadlift(),
            ExerciseData::press(),
        ];

        foreach ($this->groups as $group) {
            $this->showAddForm[$group] = false;
            $this->newName[$group] = '';
            $this->newModifier[$group] = 100.0;
        }

        $this->emitExercises();
        $this->dispatch('groups-updated', groups: $this->groups);
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
            $this->showAddForm[$this->newGroupName] = false;
            $this->newName[$this->newGroupName] = '';
            $this->newModifier[$this->newGroupName] = 100.0;
            $this->dispatch('groups-updated', groups: $this->groups);
        }

        $this->newGroupName = '';
        $this->showAddGroupModal = false;
    }

    public function getExercisesForGroup(string $group): array
    {
        return array_filter(
            $this->exercises,
            fn ($ex) => $ex->group === $group
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

    public function addExercise(string $group): void
    {
        $this->validate([
            "newName.{$group}" => 'required|string|min:1',
            "newModifier.{$group}" => 'required|numeric|min:1',
        ]);

        $this->exercises[] = new ExerciseData(
            id: $this->getNextId(),
            name: $this->newName[$group],
            modifier: $this->newModifier[$group],
            group: $group,
        );

        $this->resetAddForm($group);
        $this->showAddForm[$group] = false;
        $this->emitExercises();
    }

    public function toggleAddForm(string $group): void
    {
        $this->showAddForm[$group] = ! $this->showAddForm[$group];
        if (! $this->showAddForm[$group]) {
            $this->resetAddForm($group);
        }
    }

    public function cancelAdd(string $group): void
    {
        $this->showAddForm[$group] = false;
        $this->resetAddForm($group);
    }

    public function removeExercise(int $exerciseId): void
    {
        $this->exercises = array_values(
            array_filter($this->exercises, fn ($ex) => $ex->id !== $exerciseId)
        );
        $this->emitExercises();
    }

    public function moveExerciseUp(int $exerciseId, string $group): void
    {
        $groupExercises = [];
        $groupIndices = [];

        foreach ($this->exercises as $index => $exercise) {
            if ($exercise->group === $group) {
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

    public function moveExerciseDown(int $exerciseId, string $group): void
    {
        $groupExercises = [];
        $groupIndices = [];

        foreach ($this->exercises as $index => $exercise) {
            if ($exercise->group === $group) {
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

    protected function getNextId(): int
    {
        if (empty($this->exercises)) {
            return 1;
        }

        $maxId = max(array_map(fn ($item) => $item->id, $this->exercises));

        return $maxId + 1;
    }

    protected function resetAddForm(string $group): void
    {
        $this->newName[$group] = '';
        $this->newModifier[$group] = 100.0;
    }

    public function getListKey(string $group): string
    {
        return md5($group.json_encode(array_map(fn ($ex) => $ex->id, $this->exercises)));
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
