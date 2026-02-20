<?php

namespace App\Livewire\Training\View;

use App\Data\Training\TrainingProgramData;
use App\Models\ProgramCategory;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanProgram;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\ColorBadge;
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
        return false;
    }

    protected function getBaseQuery(): Builder
    {
        return TrainingPlanProgram::query()
            ->where('training_plan_programs.training_plan_id', $this->trainingPlan->id)
            ->with('programCategory')
            ->leftJoin('program_categories', 'training_plan_programs.program_category_id', '=', 'program_categories.id')
            ->orderBy('program_categories.name')
            ->orderBy('training_plan_programs.name')
            ->select('training_plan_programs.*');
    }

    protected function getTable(): Table
    {
        $colorLabels = ProgramCategory::query()
            ->pluck('name', 'color')
            ->all();

        return Table::make()
            ->columns([
                Text::make('name')->label('Name')->modal(),
                ColorBadge::make('categoryColor')
                    ->label('Category')
                    ->colorLabels($colorLabels),
                Relationship::make('exercises')->label('Exercises')->modal()->width('w-full'),
                Ago::make('updatedAt')->label('Last Changed'),
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

    protected function getFormModalMaxWidth(): string
    {
        return 'max-w-lg';
    }

    protected function emit(): void
    {
        $this->notifyChanged('programs');
    }
}
