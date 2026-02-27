<?php

namespace App\Livewire\Training\View;

use App\Data\Training\ProgramData;
use App\Models\ProgramCategory;
use App\Models\ExercisePlanProgram;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\ColorBadge;
use Coda\Cms\Display\DisplayFields\Relationship;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Coda\Cms\Livewire\AbstractModelList;
use Coda\Cms\Livewire\Concerns\InteractsWithParentView;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProgramList extends AbstractModelList
{
    use InteractsWithParentView;

    public Model $exercisePlan;

    protected function urlPrefix(): string
    {
        return 'tprl_';
    }

    protected function getDataClass(): string
    {
        return ProgramData::class;
    }

    protected function isSortable(): bool
    {
        return false;
    }

    protected function getBaseQuery(): Builder
    {
        return ExercisePlanProgram::query()
            ->where('exercise_plan_programs.plannable_type', get_class($this->exercisePlan))
            ->where('exercise_plan_programs.plannable_id', $this->exercisePlan->id)
            ->with('programCategory')
            ->leftJoin('program_categories', 'exercise_plan_programs.program_category_id', '=', 'program_categories.id')
            ->orderBy('program_categories.name')
            ->orderBy('exercise_plan_programs.name')
            ->select('exercise_plan_programs.*');
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

    protected function dataFromModel(Model $model): ProgramData
    {
        return ProgramData::fromExercisePlanProgram($model);
    }

    protected function createDataFromForm(array $formData): ProgramData
    {
        $data = ProgramData::from($formData);
        $data->plannable_type = get_class($this->exercisePlan);
        $data->plannable_id = $this->exercisePlan->id;

        return $data;
    }

    public function removeItem(int $id): void
    {
        $config = $this->exercisePlan->config;
        $config->removeProgramFromAllSchedules($id);
        $this->exercisePlan->config = $config;
        $this->exercisePlan->save();

        parent::removeItem($id);
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
