<?php

namespace Coda\Cms\Tests\Fixtures;

use Coda\Cms\Data\AbstractData;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Coda\Cms\Livewire\AbstractModelList;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TestCardEnabledList extends AbstractModelList
{
    protected function urlPrefix(): string
    {
        return 'til_card_';
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
                Text::make('name')->label('Name'),
                Text::make('status')->label('Status'),
            ])
            ->cards([
                Text::make('name')->label('Name'),
                Text::make('status')->label('Status'),
            ])
            ->defaultSort('name');
    }
}
