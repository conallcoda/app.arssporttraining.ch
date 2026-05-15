<?php

namespace Coda\Cms\Tests\Fixtures;

class TestManualSortItemTree extends TestItemTree
{
    protected function usesManualSorting(): bool
    {
        return true;
    }
}
