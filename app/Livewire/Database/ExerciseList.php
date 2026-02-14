<?php

namespace App\Livewire\Database;

use App\Data\Exercise\ExerciseData;
use App\Models\Exercise\Exercise;
use App\Models\Tag;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\Badge;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\Cms\Form\Action;
use Coda\Cms\Form\Fields\Text as TextField;
use Coda\Cms\Livewire\AbstractModelList;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExerciseList extends AbstractModelList
{
    protected function getDataClass(): string
    {
        return ExerciseData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return Exercise::query()->with(['category', 'equipment', 'modifiers']);
    }

    protected function dataFromModel(Model $model): AbstractData
    {
        return ExerciseData::fromExercise($model);
    }

    protected function createDataFromForm(array $formData): AbstractData
    {
        return ExerciseData::from($formData);
    }

    protected function getTable(): Table
    {
        $tagNames = Tag::query()
            ->whereIn('scope', ['exercise_category', 'exercise_equipment', 'exercise_modifiers'])
            ->pluck('name', 'id');

        $tagBadges = fn (ExerciseData $data, string $field) => collect($data->{$field})
            ->map(fn (int $id) => ['label' => $tagNames[$id] ?? '?', 'modalField' => $field])
            ->all();

        return Table::make()
            ->columns([
                Id::make(),
                Text::make('name')
                    ->label('Name')
                    ->width('w-1/3')
                    ->modal(),
                Badge::make('category')
                    ->label('Category')
                    ->source(
                        fn (ExerciseData $data) => $data->category
                            ? [['label' => $tagNames[$data->category] ?? '?', 'modalField' => 'category']]
                            : []
                    ),
                Badge::make('equipment')
                    ->label('Equipment')
                    ->source(fn (ExerciseData $data) => $tagBadges($data, 'equipment')),
                Badge::make('modifiers')
                    ->label('Modifiers')
                    ->source(fn (ExerciseData $data) => $tagBadges($data, 'modifiers')),
                Badge::make('defaults')
                    ->label('Defaults')
                    ->source(fn (ExerciseData $data) => $data->getDefaultsBadges()),
                Ago::make('updatedAt')->label('Last Changed'),
            ])
            ->sortable(['id', 'name', 'updatedAt'])
            ->filters([
                TableFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where('name', 'like', '%'.$value.'%');
                })
                    ->field(
                        TextField::make('search')
                            ->label('Search')
                            ->placeholder('Search exercises...')
                    ),
            ]);
    }

    protected function getAddAction(): Action
    {
        return parent::getAddAction()
            ->formComponent('database.exercise-form');
    }

    protected function getEditAction(): Action
    {
        return parent::getEditAction()
            ->formComponent('database.exercise-form');
    }
}
