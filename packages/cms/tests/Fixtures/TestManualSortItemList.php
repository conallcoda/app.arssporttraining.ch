<?php

namespace Coda\Cms\Tests\Fixtures;

class TestManualSortItemList extends TestItemList
{
    protected function isSortable(): bool
    {
        return true;
    }

    protected function usesManualSorting(): bool
    {
        return true;
    }

    protected function getSortColumn(): string
    {
        return 'sort_order';
    }
}
