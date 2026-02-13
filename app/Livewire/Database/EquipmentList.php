<?php

namespace App\Livewire\Database;

use App\Cms\Data\AbstractData;
use App\Cms\Display\DisplayFields\Ago;
use App\Cms\Display\DisplayFields\Id;
use App\Cms\Display\DisplayFields\Text;
use App\Cms\Display\Table;
use App\Cms\Display\TableFilter;
use App\Cms\Form\Fields\Text as TextField;
use App\Cms\Livewire\AbstractModelList;
use App\Data\Equipment\EquipmentData;
use App\Models\Tag;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EquipmentList extends AbstractModelList
{
    protected function getDataClass(): string
    {
        return EquipmentData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return Tag::query()->forScope('exercise_equipment');
    }

    protected function dataFromModel(Model $model): AbstractData
    {
        return EquipmentData::fromTag($model);
    }

    protected function createDataFromForm(array $formData): AbstractData
    {
        return EquipmentData::from($formData);
    }

    protected function getTable(): Table
    {
        return Table::make()
            ->columns([
                Id::make(),
                Text::make('name')
                    ->label('Name')
                    ->width('w-1/3')
                    ->modal(),
                Ago::make('updatedAt')->label('Last Changed'),
            ])
            ->sortable(['id', 'name', 'updatedAt'])
            ->defaultSort('name')
            ->filters([
                TableFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where('name', 'like', '%'.$value.'%');
                })
                    ->field(
                        TextField::make('search')
                            ->label('Search')
                            ->placeholder('Search equipment...')
                    ),
            ]);
    }
}
