<?php

namespace App\Livewire\Training\View;

use App\Data\Training\ExerciseProgramData;
use App\Models\ExerciseProgram;
use App\Models\ProgramCategory;
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
        return ExerciseProgramData::class;
    }

    protected function isSortable(): bool
    {
        return false;
    }

    protected function getBaseQuery(): Builder
    {
        $ids = $this->exercisePlan->programIds();

        return ExerciseProgram::query()
            ->whereIn('exercise_programs.id', $ids)
            ->with('programCategory')
            ->leftJoin('program_categories', 'exercise_programs.program_category_id', '=', 'program_categories.id')
            ->orderBy('program_categories.name')
            ->orderBy('exercise_programs.name')
            ->select('exercise_programs.*');
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

    protected function dataFromModel(Model $model): ExerciseProgramData
    {
        return ExerciseProgramData::fromModel($model);
    }

    protected function createDataFromForm(array $formData): ExerciseProgramData
    {
        return ExerciseProgramData::from($formData);
    }

    public function removeItem(int $id): void
    {
        $config = $this->exercisePlan->config;
        $config->removeProgramFromAllSchedules($id);
        $this->exercisePlan->config = $config;
        $this->exercisePlan->save();

        $this->resetState();
        $this->refreshKey++;
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
