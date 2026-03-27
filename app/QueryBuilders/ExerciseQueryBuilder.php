<?php

namespace App\QueryBuilders;

use Coda\Cms\QueryBuilder\DefaultQueryBuilder;
use Coda\Cms\QueryBuilder\QueryExpressionFilter;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class ExerciseQueryBuilder extends DefaultQueryBuilder
{
    /** @return array<int, string|AllowedSort> */
    public function getDefinedSorts(): array
    {
        return $this->resolveSorts([
            'id',
            'name',
            'type',
            'updatedAt',
            'coach => owner.surname.concat("forename")',
            'category => category.name',
        ]);
    }

    /** @return array<int, string|AllowedFilter> */
    public function getDefinedFilters(): array
    {
        return [
            QueryExpressionFilter::make('search', 'name contains $value or type contains $value', [
                'fields' => ['name', 'type'],
            ]),
            AllowedFilter::exact('type'),
        ];
    }
}
