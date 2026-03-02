<?php

namespace App\Livewire\Training;

use App\Data\Training\ExerciseProgramData;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramCategory;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\ColorBadge;
use Coda\Cms\Display\DisplayFields\Relationship;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\DisplayFields\View;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\Cms\Form\Fields\Select;
use Coda\Cms\Form\Fields\Text as TextField;
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
            ->leftJoin('exercise_program_categories', 'exercise_programs.program_category_id', '=', 'exercise_program_categories.id')
            ->select('exercise_programs.*');
    }

    protected function buildItemsQuery()
    {
        $query = parent::buildItemsQuery();

        if ($this->sort === '') {
            $query->orderBy('exercise_program_categories.name')
                ->orderBy('exercise_programs.name');
        }

        return $query;
    }

    protected function getTable(): Table
    {
        $colorLabels = ExerciseProgramCategory::query()
            ->pluck('name', 'color')
            ->all();

        $categoryOptions = ExerciseProgramCategory::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return Table::make()
            ->columns([
                Text::make('id')->label('ID')->width('w-16')->prefix('#'),
                View::make('name', ExerciseProgramView::class)->label('Name'),
                ColorBadge::make('categoryColor')
                    ->label('Category')
                    ->colorLabels($colorLabels),
                Relationship::make('exercises')->label('Exercises')->modal()->width('w-full'),
                Ago::make('updatedAt')->label('Last Changed'),
            ])
            ->sortable(['id', 'name', 'updatedAt'])
            ->filters([
                TableFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where('exercise_programs.name', 'like', '%'.$value.'%');
                })
                    ->field(
                        TextField::make('search')
                            ->label('Search')
                            ->placeholder('Search programs...')
                    ),
                TableFilter::exact('category', 'program_category_id')
                    ->field(
                        Select::make('category')
                            ->label('Category')
                            ->placeholder('All categories')
                            ->options($categoryOptions)
                    ),
            ])
            ->limit(100);
    }
}
