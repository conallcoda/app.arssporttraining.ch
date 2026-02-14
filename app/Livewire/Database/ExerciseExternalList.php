<?php

namespace App\Livewire\Database;

use App\Cms\Data\AbstractData;
use App\Cms\Display\DisplayFields\Ago;
use App\Cms\Display\DisplayFields\Breadcrumb;
use App\Cms\Display\DisplayFields\Id;
use App\Cms\Display\DisplayFields\TextWithBadgeGroups;
use App\Cms\Display\Table;
use App\Cms\Display\TableFilter;
use App\Cms\Form\Fields\Text as TextField;
use App\Cms\Livewire\AbstractModelList;
use App\Data\Exercise\ExerciseExternalData;
use App\Models\Exercise\ExerciseExternal;
use App\Models\Tag;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExerciseExternalList extends AbstractModelList
{
    protected function getDataClass(): string
    {
        return ExerciseExternalData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return ExerciseExternal::query()->with(['category.ancestorsAndSelf', 'equipment', 'modifiers']);
    }

    protected function dataFromModel(Model $model): AbstractData
    {
        return ExerciseExternalData::fromExerciseExternal($model);
    }

    protected function createDataFromForm(array $formData): AbstractData
    {
        return ExerciseExternalData::from($formData);
    }

    protected function getActions(): array
    {
        return [
            $this->getEditAction(),
        ];
    }

    protected function getTable(): Table
    {
        $tagNames = Tag::query()
            ->whereIn('scope', ['exercise_category', 'exercise_equipment', 'exercise_modifiers'])
            ->pluck('name', 'id');

        $tagBadges = fn (ExerciseExternalData $data, string $field) => collect($data->{$field})
            ->map(fn (int $id) => ['label' => $tagNames[$id] ?? '?'])
            ->all();

        return Table::make()
            ->columns([
                Id::make(),
                TextWithBadgeGroups::make('name')
                    ->label('Exercise')
                    ->modal()
                    ->badgeGroup(
                        'equipment',
                        fn (ExerciseExternalData $data) => $tagBadges($data, 'equipment'),
                        'blue',
                    )
                    ->badgeGroup(
                        'modifiers',
                        fn (ExerciseExternalData $data) => $tagBadges($data, 'modifiers'),
                        '',
                    ),
                Breadcrumb::make('categoryPath')
                    ->label('Category')
                    ->source(fn (ExerciseExternalData $data) => $data->categoryPath),
                Ago::make('updatedAt')->label('Last Changed'),
            ])
            ->sortable(['id', 'updatedAt'])
            ->filters([
                TableFilter::callback('search', function (Builder $query, mixed $value): void {
                    $terms = array_filter(explode(' ', trim($value)));

                    $query->where(function (Builder $outer) use ($terms): void {
                        foreach ($terms as $term) {
                            $outer->orWhere(function (Builder $q) use ($term): void {
                                $q->where('name', 'like', '%'.$term.'%')
                                    ->orWhereHas('category', fn (Builder $q) => $q->where('name', 'like', '%'.$term.'%'))
                                    ->orWhereHas('equipment', fn (Builder $q) => $q->where('name', 'like', '%'.$term.'%'))
                                    ->orWhereHas('modifiers', fn (Builder $q) => $q->where('name', 'like', '%'.$term.'%'));
                            });
                        }
                    });
                })
                    ->field(
                        TextField::make('search')
                            ->label('Search')
                    ),
            ]);
    }
}
