<?php

namespace App\QueryBuilders;

use App\Cms\QueryBuilder\DefaultQueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class TagQueryBuilder extends DefaultQueryBuilder
{
    /** @return array<int, string|\Spatie\QueryBuilder\AllowedSort> */
    public function getDefinedSorts(): array
    {
        return [
            'id',
            'name',
            AllowedSort::field('updatedAt', 'updated_at'),
        ];
    }

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
