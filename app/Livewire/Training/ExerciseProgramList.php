<?php

namespace App\Livewire\Training;

use App\Data\Training\ExerciseProgramData;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Tag;
use App\Support\OwnershipTabs;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\Badge;
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

    protected function getTabs(): array
    {
        return OwnershipTabs::make('Programs')->toArray();
    }

    protected function getDefaultTabKey(): ?string
    {
        return OwnershipTabs::make('Programs')->defaultTab($this->getBaseQuery());
    }

    protected function getBaseQuery(): Builder
    {
        return ExerciseProgram::query()
            ->whereNull('exercise_programs.parent_id')
            ->whereNull('exercise_programs.parent_type')
            ->with(['exerciseCategory', 'internalTags', 'owner'])
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

        $tagNames = Tag::query()
            ->forScope('program_internal')
            ->pluck('name', 'id');

        $exerciseCategoryOptions = Tag::query()
            ->forScope('exercise_category')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return Table::make()
            ->columns([
                Text::make('id')->label(__('ID'))->width('w-16')->prefix('#'),
                View::make('name', ExerciseProgramView::class)->label(__('Name')),
                Badge::make('coach')
                    ->label(__('Coach'))
                    ->source(fn (ExerciseProgramData $data) => [
                        [
                            'label' => $data->ownerName ?? __('Unassigned'),
                            'color' => $data->ownerColor,
                            'modalField' => 'owner_id',
                        ],
                    ]),
                ColorBadge::make('exerciseCategoryColor')
                    ->label(__('Category'))
                    ->colorLabels($exerciseCategoryColorLabels),
                Relationship::make('exercises')->label(__('Exercises'))->modal()->width('w-full'),
                Badge::make('internalTags')
                    ->label(__('Tags'))
                    ->source(fn (ExerciseProgramData $data) => collect($data->internalTags)
                        ->map(fn (int $id) => ['label' => $tagNames[$id] ?? '?'])
                        ->all()
                    ),
                Ago::make('updatedAt')->label(__('Last Changed')),
            ])
            ->sortable(['id', 'name', 'updatedAt'])
            ->filters([
                TableFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where('exercise_programs.name', 'like', '%'.$value.'%');
                })
                    ->field(
                        TextField::make('search')
                            ->label(__('Search'))
                            ->placeholder(__('Search programs...'))
                    ),
                TableFilter::exact('category', 'exercise_category_id')
                    ->field(
                        Select::make('category')
                            ->label(__('Category'))
                            ->placeholder(__('All categories'))
                            ->options($exerciseCategoryOptions)
                    ),
            ])
            ->limit(100);
    }
}
