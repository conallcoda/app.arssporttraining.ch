<?php

namespace App\Livewire\Training\View;

use App\Data\Training\TrainingProgramData;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanProgram;
use Coda\Cms\Display\DisplayFields\Relationship;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Coda\Cms\Livewire\AbstractModelList;
use Coda\Cms\Livewire\Concerns\InteractsWithParentView;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TrainingProgramList extends AbstractModelList
{
    use InteractsWithParentView;

    public TrainingPlan $trainingPlan;

    protected function urlPrefix(): string
    {
        return 'tprl_';
    }

    protected function getDataClass(): string
    {
        return TrainingProgramData::class;
    }

    protected function isSortable(): bool
    {
        return true;
    }

    protected function getBaseQuery(): Builder
    {
        return TrainingPlanProgram::query()
            ->where('training_plan_id', $this->trainingPlan->id);
    }

    protected function getTable(): Table
    {
        return Table::make()
            ->columns([
                Text::make('name')->label('Name')->modal(),
                Relationship::make('exercises')->label('Exercises')->modal()->width('w-full'),
            ])
            ->limit(100);
    }

    protected function dataFromModel(Model $model): TrainingProgramData
    {
        return TrainingProgramData::fromTrainingPlanProgram($model);
    }

    protected function createDataFromForm(array $formData): TrainingProgramData
    {
        $data = TrainingProgramData::from($formData);
        $data->training_plan_id = $this->trainingPlan->id;

        return $data;
    }

    protected function emit(): void
    {
        $this->notifyChanged('programs');
    }
}
