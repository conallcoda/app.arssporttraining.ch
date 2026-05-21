<?php

namespace Coda\Cms\Tests\Fixtures;

use Coda\Cms\Data\AbstractData;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Coda\Cms\Livewire\AbstractModelList;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TestNestedFieldItemList extends AbstractModelList
{
    protected function urlPrefix(): string
    {
        return 'tnfil_';
    }

    protected function getDataClass(): string
    {
        return CmsTestItemWithNestedData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return CmsTestItem::query();
    }

    protected function dataFromModel(Model $model): AbstractData
    {
        return CmsTestItemWithNestedData::fromModel($model);
    }

    protected function getTable(): Table
    {
        return Table::make()
            ->columns([
                Text::make('name')->label('Name'),
                Text::make('profile.subtitle')->label('Subtitle'),
            ]);
    }
}
