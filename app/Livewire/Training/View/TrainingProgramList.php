<?php

namespace App\Livewire\Training\View;

use App\Cms\Form\TableColumn;
use App\Livewire\Concerns\AbstractModelList;
use App\Livewire\Concerns\InteractsWithParentView;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanProgram;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TrainingProgramList extends AbstractModelList
{
    use InteractsWithParentView;

    public TrainingPlan $trainingPlan;

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

    protected function getPerPage(): int
    {
        return 100;
    }

    protected function emit(): void
    {
        parent::emit();
        $this->notifyChanged('programs');
    }
}
