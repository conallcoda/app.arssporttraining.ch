<?php

namespace App\Livewire\Training\View;

use App\Data\Form\TableColumn;
use App\Livewire\Concerns\AbstractModelList;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanProgram;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TrainingProgramList extends AbstractModelList
{
    public TrainingPlan $trainingPlan;

    public bool $compact = true;

    protected function getDataClass(): string
    {
        return TrainingProgramData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return TrainingPlanProgram::query()
            ->where('training_plan_id', $this->trainingPlan->id);
    }

    protected function getColumns(): array
    {
        return [
            TableColumn::text('name')->label('Name')->modal(),
            TableColumn::relationship('exercises')->label('Exercises')->modal()->width('w-full'),
        ];
    }

    protected function dataFromModel(Model $model): TrainingProgramData
    {
        return TrainingProgramData::fromTrainingPlanProgram($model);
    }

    protected function createDataFromForm(): TrainingProgramData
    {
        $data = TrainingProgramData::from($this->data);
        $data->training_plan_id = $this->trainingPlan->id;

        return $data;
    }
}
