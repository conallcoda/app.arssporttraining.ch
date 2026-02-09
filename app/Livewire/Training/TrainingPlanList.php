<?php

namespace App\Livewire\Training;

use App\Cms\Form\RowAction;
use App\Cms\Form\TableColumn;
use App\Cms\Livewire\AbstractModelList;
use App\Data\Training\TrainingPlanData;
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
            RowAction::make('confirmDuplicate', 'Duplicate')->icon('copy'),
        ];
    }

    protected function performDuplicate(array $data): void
    {
        if (empty($data['id'])) {
            return;
        }

        $original = TrainingPlan::with(['users', 'userGroups', 'programs.exercises'])->findOrFail($data['id']);

        $newPlan = $original->replicate();
        $newPlan->name = $data['name'] ?? $original->name.' (Copy)';
        $newPlan->save();

        $usersWithSort = [];
        foreach ($original->users as $user) {
            $usersWithSort[$user->id] = ['sort' => $user->pivot->sort];
        }
        $newPlan->users()->sync($usersWithSort);

        $groupsWithSort = [];
        foreach ($original->userGroups as $group) {
            $groupsWithSort[$group->id] = ['sort' => $group->pivot->sort];
        }
        $newPlan->userGroups()->sync($groupsWithSort);

        foreach ($original->programs as $program) {
            $newProgram = $program->replicate();
            $newProgram->training_plan_id = $newPlan->id;
            $newProgram->save();

            $exercisesWithPivot = [];
            foreach ($program->exercises as $exercise) {
                $exercisesWithPivot[$exercise->id] = [
                    'sort' => $exercise->pivot->sort,
                    'config' => $exercise->pivot->config,
                ];
            }
            $newProgram->exercises()->sync($exercisesWithPivot);
        }
    }
}
