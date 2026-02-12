<?php

namespace App\Livewire\Database;

use App\Cms\Data\AbstractData;
use App\Cms\Display\DisplayFields\Ago;
use App\Cms\Display\DisplayFields\Badge;
use App\Cms\Display\DisplayFields\Id;
use App\Cms\Display\DisplayFields\Text;
use App\Cms\Display\Table;
use App\Cms\Display\TableFilter;
use App\Cms\Form\Fields\Select;
use App\Cms\Form\Fields\Text as TextField;
use App\Cms\Livewire\AbstractModelList;
use App\Data\Exercise\ExerciseData;
use App\Data\Exercise\ExerciseType;
use App\Models\Exercise\Exercise;
use App\Models\Tag;
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

        $tagBadges = fn(ExerciseData $data, string $field) => collect($data->{$field})
            ->map(fn(int $id) => ['label' => $tagNames[$id] ?? '?', 'modalField' => $field])
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
                        fn(ExerciseData $data) => $data->category
                            ? [['label' => $tagNames[$data->category] ?? '?', 'modalField' => 'category']]
                            : []
                    ),
                Badge::make('equipment')
                    ->label('Equipment')
                    ->source(fn(ExerciseData $data) => $tagBadges($data, 'equipment')),
                Badge::make('modifiers')
                    ->label('Modifiers')
                    ->source(fn(ExerciseData $data) => $tagBadges($data, 'modifiers')),
                Badge::make('defaults')
                    ->label('Defaults')
                    ->source(fn(ExerciseData $data) => $data->getDefaultsBadges()),
                Ago::make('updatedAt')->label('Last Changed'),
            ])
            ->sortable(['id', 'name', 'type', 'updatedAt'])
            ->filters([
                TableFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where(function (Builder $subQuery) use ($value): void {
                        $subQuery->where('name', 'like', '%' . $value . '%')
                            ->orWhere('type', 'like', '%' . $value . '%');
                    });
                })
                    ->field(
                        TextField::make('search')
                            ->label('Search')
                            ->placeholder('Search exercises...')
                    ),
                TableFilter::exact('type')
                    ->field(
                        Select::make('type')
                            ->label('Type')
                            ->options(
                                collect(ExerciseType::cases())
                                    ->mapWithKeys(fn(ExerciseType $case) => [$case->value => $case->label()])
                                    ->toArray()
                            )
                            ->placeholder('All types')
                    ),
            ]);
    }
}
