<?php

namespace Coda\Cms\Tests\Fixtures;

use Coda\Cms\Data\AbstractData;
use Coda\Cms\Livewire\AbstractModelTree;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TestItemTree extends AbstractModelTree
{
    protected function urlPrefix(): string
    {
        return 'tit_';
    }

    protected function getDataClass(): string
    {
        return CmsTestItemData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return CmsTestItem::query();
    }

    protected function getRootsQuery(): Builder
    {
        return CmsTestItem::query()->roots();
    }

    protected function dataFromModel(Model $model): AbstractData
    {
        return CmsTestItemData::fromModel($model);
    }
}
