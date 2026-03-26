<?php

namespace Coda\Cms\Tests\Fixtures;

use Coda\Cms\Data\AbstractData;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\Cms\Form\Fields\Select;
use Coda\Cms\Form\Fields\Text as TextField;
use Coda\Cms\Livewire\AbstractModelList;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TestItemList extends AbstractModelList
{
    protected function urlPrefix(): string
    {
        return 'til_';
    }

    protected function getDataClass(): string
    {
        return CmsTestItemData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return CmsTestItem::query();
    }

    protected function dataFromModel(Model $model): AbstractData
    {
        return CmsTestItemData::fromModel($model);
    }

    protected function getTable(): Table
    {
        return Table::make()
            ->columns([
                Id::make(),
                Text::make('name')->label('Name')->modal(),
                Text::make('status')->label('Status'),
            ])
            ->sortable(['id', 'name', 'priority'])
            ->defaultSort('name')
            ->filters([
                TableFilter::exact('status')->field(
                    Select::make('status')->label('Status')
                        ->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'])
                ),
                TableFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where('name', 'like', "%{$value}%");
                })->field(
                    TextField::make('search')->label('Search')->placeholder('Search...')
                ),
            ]);
    }
}
