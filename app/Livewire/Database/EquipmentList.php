<?php

namespace App\Livewire\Database;

use App\Data\Exercise\ExerciseEquipmentData;
use App\Models\Tag;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\Cms\Livewire\AbstractModelList;
use Coda\FormKit\Fields\Text as TextField;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EquipmentList extends AbstractModelList
{
    protected function urlPrefix(): string
    {
        return 'eql_';
    }

    protected function getDataClass(): string
    {
        return ExerciseEquipmentData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return Tag::query()->forScope('exercise_equipment');
    }

    protected function dataFromModel(Model $model): AbstractData
    {
        return ExerciseEquipmentData::fromTag($model);
    }

    protected function createDataFromForm(array $formData): AbstractData
    {
        return ExerciseEquipmentData::from($formData);
    }

    protected function getTable(): Table
    {
        return Table::make()
            ->columns([
                Id::make(),
                Text::make('name')
                    ->label(__('Name'))
                    ->width('w-1/3')
                    ->modal(),
                Ago::make('updatedAt')->label(__('Last Changed')),
            ])
            ->sortable(['id', 'name', 'updatedAt'])
            ->defaultSort('name')
            ->filters([
                TableFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where('name', 'like', '%'.$value.'%');
                })
                    ->field(
                        TextField::make('search')
                            ->label(__('Search'))
                            ->placeholder(__('Search equipment...'))
                    ),
            ]);
    }
}
