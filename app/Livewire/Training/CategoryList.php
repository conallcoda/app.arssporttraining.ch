<?php

namespace App\Livewire\Training;

use App\Data\Training\CategoryData;
use App\Models\Tag;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\ColorBadge;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\Cms\Form\Fields\Text as TextField;
use Coda\Cms\Livewire\AbstractModelList;
use Coda\Cms\Support\ColorPalette;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CategoryList extends AbstractModelList
{
    protected function urlPrefix(): string
    {
        return 'cl_';
    }

    protected function getDataClass(): string
    {
        return CategoryData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return Tag::query()->forScope('exercise_category')->whereNull('parent_id');
    }

    protected function dataFromModel(Model $model): AbstractData
    {
        return CategoryData::fromTag($model);
    }

    protected function createDataFromForm(array $formData): AbstractData
    {
        return CategoryData::from($formData);
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
                Text::make('shortName')
                    ->label(__('Short Name')),
                ColorBadge::make('color')
                    ->label(__('Color'))
                    ->colorLabels(ColorPalette::COLORS),
                Ago::make('updatedAt')->label(__('Last Changed')),
            ])
            ->sortable(['id', 'name', 'color', 'updatedAt'])
            ->defaultSort('name')
            ->filters([
                TableFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where('name', 'like', '%'.$value.'%');
                })
                    ->field(
                        TextField::make('search')
                            ->label(__('Search'))
                            ->placeholder(__('Search categories...'))
                    ),
            ]);
    }
}
