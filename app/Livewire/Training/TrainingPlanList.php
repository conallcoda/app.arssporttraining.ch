<?php

namespace App\Livewire\Training;

use App\Data\Form\RowAction;
use App\Data\Form\TableColumn;
use App\Livewire\Concerns\AbstractModelList;
use App\Models\TrainingPlan;
use Illuminate\Contracts\Database\Eloquent\Builder;

class TrainingPlanList extends AbstractModelList
{
    protected function getDataClass(): string
    {
        return TrainingPlanData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return TrainingPlan::query();
    }

    protected function getColumns(): array
    {
        return [
            TableColumn::id(),
            TableColumn::view('name', TrainingPlanView::class)->label('Name'),
            TableColumn::relationship('users')->label('Athletes'),
            TableColumn::relationship('userGroups')->label('Athlete Groups'),
        ];
    }

    protected function getRowActions(): array
    {
        return [
            RowAction::make('duplicate', 'Duplicate')->icon('copy'),
        ];
    }

    public function duplicate(int $id): void
    {
        $original = TrainingPlan::with(['users', 'userGroups', 'programs.exercises'])->findOrFail($id);

        $newPlan = $original->replicate();
        $newPlan->name = $original->name.' (Copy)';
        $newPlan->save();

        $newPlan->users()->sync($original->users->pluck('id'));
        $newPlan->userGroups()->sync($original->userGroups->pluck('id'));

        foreach ($original->programs as $program) {
            $newProgram = $program->replicate();
            $newProgram->training_plan_id = $newPlan->id;
            $newProgram->save();

            $exercisesWithPivot = [];
            foreach ($program->exercises as $exercise) {
                $exercisesWithPivot[$exercise->id] = [
                    'sort' => $exercise->pivot->sort,
                    'extra' => $exercise->pivot->extra,
                ];
            }
            $newProgram->exercises()->sync($exercisesWithPivot);
        }

        unset($this->items);
        $this->refreshKey++;
        $this->emit();
    }
}
