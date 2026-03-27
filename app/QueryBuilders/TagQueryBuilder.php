<?php

namespace App\QueryBuilders;

use Coda\Cms\QueryBuilder\DefaultQueryBuilder;
use Coda\Cms\QueryBuilder\QueryExpressionFilter;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class TagQueryBuilder extends DefaultQueryBuilder
{
    /** @return array<int, string|AllowedSort> */
    public function getDefinedSorts(): array
    {
        return $this->resolveSorts([
            'id',
            'name',
            'color',
            'updatedAt',
        ]);
    }

    /** @return array<int, string|AllowedFilter> */
    public function getDefinedFilters(): array
    {
        return [
            QueryExpressionFilter::make('search', 'name contains $value', [
                'fields' => ['name'],
            ]),
        ];
    }
}
