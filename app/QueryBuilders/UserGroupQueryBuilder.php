<?php

namespace App\QueryBuilders;

use App\Support\Sorts\CoachSort;
use Coda\Cms\QueryBuilder\DefaultQueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class UserGroupQueryBuilder extends DefaultQueryBuilder
{
    /** @return array<int, string|\Spatie\QueryBuilder\AllowedSort> */
    public function getDefinedSorts(): array
    {
        return [
            'id',
            'name',
            AllowedSort::field('updatedAt', 'updated_at'),
            CoachSort::make('coach', 'user_groups'),
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
