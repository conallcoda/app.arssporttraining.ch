<?php

namespace App\Livewire\Database;

use App\Data\Modifier\ModifierData;
use App\Models\Tag;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\Cms\Form\Fields\Text as TextField;
use Coda\Cms\Livewire\AbstractModelList;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ModifiersList extends AbstractModelList
{
    protected function urlPrefix(): string
    {
        return 'ml_';
    }

    protected function getDataClass(): string
    {
        return ModifierData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return Tag::query()->forScope('exercise_modifiers');
    }

    protected function dataFromModel(Model $model): AbstractData
    {
        return ModifierData::fromTag($model);
    }

    protected function createDataFromForm(array $formData): AbstractData
    {
        return ModifierData::from($formData);
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
                            ->placeholder('Search modifiers...')
                    ),
            ]);
    }
}
