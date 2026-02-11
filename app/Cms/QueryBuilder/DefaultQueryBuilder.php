<?php

namespace App\Cms\QueryBuilder;

use Spatie\QueryBuilder\QueryBuilder;

class DefaultQueryBuilder extends QueryBuilder
{
    /** @return array<int, string|\Spatie\QueryBuilder\AllowedSort> */
    public function getDefinedSorts(): array
    {
        return [];
    }

    /** @return array<int, string|\Spatie\QueryBuilder\AllowedFilter> */
    public function getDefinedFilters(): array
    {
        return [];
    }
}
