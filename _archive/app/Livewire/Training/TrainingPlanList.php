<?php

namespace App\Livewire\Training;

use App\Data\Training\TrainingPlanData;
use App\Models\TrainingPlan;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\Relationship;
use Coda\Cms\Display\DisplayFields\View;
use Coda\Cms\Display\Table;
use Coda\Cms\Form\Action;
use Coda\Cms\Form\DuplicateNameForm;
use Coda\Cms\Livewire\AbstractModelList;
use Illuminate\Contracts\Database\Eloquent\Builder;

class TrainingPlanList extends AbstractModelList
{
    protected function urlPrefix(): string
    {
        return 'tpl_';
    }

    protected function getDataClass(): string
    {
        return TrainingPlanData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return TrainingPlan::query();
    }

    protected function getTable(): Table
    {
        return Table::make()
            ->columns([
                Id::make(),
                View::make('name', TrainingPlanView::class)->label('Name'),
                Relationship::make('users')->label('Athletes'),
                Relationship::make('userGroups')->label('Athlete Groups'),
            ])
            ->actions([
                Action::make('duplicate', 'Duplicate')
                    ->rowMenu()
                    ->icon('copy')
                    ->formModal(DuplicateNameForm::class, 'Duplicate '.$this->getEntityName(), 'Duplicate')
                    ->handler('handleDuplicateSubmitted')
                    ->prepareData(fn (TrainingPlan $model) => [
                        'id' => $model->id,
                        'name' => ($model->name ?? '').' (Copy)',
                    ]),
            ]);
    }

    public function handleDuplicateSubmitted(array $data): void
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
            $newProgram->plannable_type = TrainingPlan::class;
            $newProgram->plannable_id = $newPlan->id;
            $newProgram->save();

            $exercisesWithPivot = [];
            foreach ($program->exercises as $exercise) {
                $exercisesWithPivot[$exercise->id] = [
                    'sort' => $exercise->pivot->sort,
                ];
            }
            $newProgram->exercises()->sync($exercisesWithPivot);
        }

        $this->resetState();
        $this->refreshKey++;
    }
}
