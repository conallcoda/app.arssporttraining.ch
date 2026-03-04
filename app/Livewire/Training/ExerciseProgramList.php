<?php

namespace App\Livewire\Training;

use App\Data\Training\ExerciseProgramData;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Tag;
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
            ->whereNull('owner_id')
            ->whereNull('owner_type')
            ->with(['exerciseCategory'])
            ->leftJoin('tags as exercise_category_tags', 'exercise_programs.exercise_category_id', '=', 'exercise_category_tags.id')
            ->select('exercise_programs.*');
    }

    protected function buildItemsQuery()
    {
        $query = parent::buildItemsQuery();

        if ($this->sort === '') {
            $query->orderBy('exercise_category_tags.name')
                ->orderBy('exercise_programs.name');
        }

        return $query;
    }

    protected function getTable(): Table
    {
        $exerciseCategoryColorLabels = Tag::query()
            ->forScope('exercise_category')
            ->whereNull('parent_id')
            ->pluck('name', 'color')
            ->all();

        $exerciseCategoryOptions = Tag::query()
            ->forScope('exercise_category')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return Table::make()
            ->columns([
                Text::make('id')->label('ID')->width('w-16')->prefix('#'),
                View::make('name', ExerciseProgramView::class)->label('Name'),
                ColorBadge::make('exerciseCategoryColor')
                    ->label('Category')
                    ->colorLabels($exerciseCategoryColorLabels),
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
                TableFilter::exact('category', 'exercise_category_id')
                    ->field(
                        Select::make('category')
                            ->label('Category')
                            ->placeholder('All categories')
                            ->options($exerciseCategoryOptions)
                    ),
            ])
            ->limit(100);
    }
}
