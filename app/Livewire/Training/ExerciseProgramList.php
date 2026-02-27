<?php

namespace App\Livewire\Training;

use App\Data\Training\ExerciseProgramData;
use App\Models\ExerciseProgram;
use App\Models\ProgramCategory;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\ColorBadge;
use Coda\Cms\Display\DisplayFields\Relationship;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Coda\Cms\Livewire\AbstractModelList;
use Illuminate\Contracts\Database\Eloquent\Builder;

class ExerciseProgramList extends AbstractModelList
{
    protected function urlPrefix(): string
    {
        return 'epl_';
    }

    protected function getDataClass(): string
    {
        return ExerciseProgramData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return ExerciseProgram::query()
            ->with('programCategory')
            ->leftJoin('program_categories', 'exercise_programs.program_category_id', '=', 'program_categories.id')
            ->orderBy('program_categories.name')
            ->orderBy('exercise_programs.name')
            ->select('exercise_programs.*');
    }

    protected function isSortable(): bool
    {
        return false;
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
}
