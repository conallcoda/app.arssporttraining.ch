<?php

namespace App\QueryBuilders;

use Coda\Cms\QueryBuilder\DefaultQueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class UserGroupQueryBuilder extends DefaultQueryBuilder
{
    /** @return array<int, string|\Spatie\QueryBuilder\AllowedFilter> */
    public function getDefinedFilters(): array
    {
        return [
            AllowedFilter::callback('search', function ($query, $value): void {
                $query->where('name', 'like', '%'.$value.'%');
            }),
        ];
    }
}
