<?php

namespace App\Livewire\Training;

use App\Data\Training\ExercisePlanData;
use App\Models\ExercisePlan;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\Relationship;
use Coda\Cms\Display\DisplayFields\View;
use Coda\Cms\Display\Table;
use Coda\Cms\Form\Action;
use Coda\Cms\Form\DuplicateNameForm;
use Coda\Cms\Livewire\AbstractModelList;
use Illuminate\Contracts\Database\Eloquent\Builder;

class ExercisePlanList extends AbstractModelList
{
    protected function urlPrefix(): string
    {
        return 'epl_';
    }

    protected function getDataClass(): string
    {
        return ExercisePlanData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return ExercisePlan::query()->with('programs');
    }

    protected function getTable(): Table
    {
        return Table::make()
            ->columns([
                Id::make(),
                View::make('name', ExercisePlanView::class)->label('Name'),
                Relationship::make('programs')->label('Programs'),
            ])
            ->actions([
                Action::make('duplicate', 'Duplicate')
                    ->rowMenu()
                    ->icon('copy')
                    ->formModal(DuplicateNameForm::class, 'Duplicate '.$this->getEntityName(), 'Duplicate')
                    ->handler('handleDuplicateSubmitted')
                    ->prepareData(fn (ExercisePlan $model) => [
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

        $original = ExercisePlan::with(['programs.exercises'])->findOrFail($data['id']);

        $newTemplate = $original->replicate();
        $newTemplate->name = $data['name'] ?? $original->name.' (Copy)';
        $newTemplate->save();

        foreach ($original->programs as $program) {
            $newProgram = $program->replicate();
            $newProgram->plannable_type = ExercisePlan::class;
            $newProgram->plannable_id = $newTemplate->id;
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
