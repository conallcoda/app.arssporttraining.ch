<?php

namespace App\Livewire\Training\View;

use App\Data\Form\TableColumn;
use App\Livewire\Concerns\AbstractModelList;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseType\StrengthDefaults;
use App\Models\TrainingPlanProgram;
use App\Models\TrainingPlanProgramExercise;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\View;

class ProgramExerciseList extends AbstractModelList
{
    public TrainingPlanProgram $program;

    public bool $compact = true;

    protected function getDataClass(): string
    {
        return ProgramExerciseData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return TrainingPlanProgramExercise::query()
            ->where('training_plan_program_id', $this->program->id)
            ->with('exercise')
            ->orderBy('sort');
    }

    protected function getColumns(): array
    {
        return [
            TableColumn::text('name')
                ->label('Name')
                ->width('w-1/3')
                ->modal(),
            TableColumn::text('settings')
                ->label('Settings')
                ->badge()
                ->source(fn (ProgramExerciseData $data) => $data->getSettingsBadges()),
        ];
    }

    protected function dataFromModel(Model $model): ProgramExerciseData
    {
        return ProgramExerciseData::fromPivot($model);
    }

    protected function createDataFromForm(): ProgramExerciseData
    {
        $exerciseId = $this->data['exercise_id'] ?? null;

        if ($exerciseId && $this->editingId === null) {
            $exercise = Exercise::find($exerciseId);
            if ($exercise) {
                $defaults = StrengthDefaults::fromExercise($exercise);
                $maxSort = TrainingPlanProgramExercise::where('training_plan_program_id', $this->program->id)
                    ->max('sort') ?? -1;

                return new ProgramExerciseData(
                    id: null,
                    training_plan_program_id: $this->program->id,
                    exercise_id: $exerciseId,
                    name: $exercise->name,
                    sort: $maxSort + 1,
                    oneRepMaxModifier: $this->data['oneRepMaxModifier'] ?? $defaults->oneRepMaxModifier,
                    startingReps: $this->data['startingReps'] ?? $defaults->startingReps,
                    timeUnderTension: $this->data['timeUnderTension'] ?? $defaults->timeUnderTension,
                    rest: $this->data['rest'] ?? $defaults->rest,
                );
            }
        }

        $data = ProgramExerciseData::from($this->data);
        $data->training_plan_program_id = $this->program->id;

        return $data;
    }

    public function updatedDataExerciseId($value): void
    {
        if (! $value || $this->editingId !== null) {
            return;
        }

        $exercise = Exercise::find($value);
        if ($exercise) {
            $defaults = StrengthDefaults::fromExercise($exercise);
            $this->data['oneRepMaxModifier'] = $defaults->oneRepMaxModifier;
            $this->data['startingReps'] = $defaults->startingReps;
            $this->data['timeUnderTension'] = $defaults->timeUnderTension;
            $this->data['rest'] = $defaults->rest;
        }
    }

    public function moveUp(int $id): void
    {
        $this->move($id, -1);
    }

    public function moveDown(int $id): void
    {
        $this->move($id, 1);
    }

    protected function move(int $id, int $direction): void
    {
        $items = TrainingPlanProgramExercise::query()
            ->where('training_plan_program_id', $this->program->id)
            ->orderBy('sort')
            ->get();

        $currentIndex = $items->search(fn ($item) => $item->id === $id);

        if ($currentIndex === false) {
            return;
        }

        $newIndex = $currentIndex + $direction;

        if ($newIndex < 0 || $newIndex >= $items->count()) {
            return;
        }

        $items[$currentIndex]->sort = $newIndex;
        $items[$newIndex]->sort = $currentIndex;

        $items[$currentIndex]->save();
        $items[$newIndex]->save();

        unset($this->items);
        $this->refreshKey++;
    }

    protected function getPerPage(): int
    {
        return 100;
    }

    protected function getEntitySlug(): string
    {
        return 'program-exercise-'.$this->program->id;
    }

    protected function getEntityName(): string
    {
        return 'Exercise';
    }

    public function render(): View
    {
        return view('livewire.training.view.program-exercise-list', [
            'modalName' => $this->getModalName(),
            'deleteModalName' => $this->getDeleteModalName(),
            'entityName' => $this->getEntityName(),
        ]);
    }
}
