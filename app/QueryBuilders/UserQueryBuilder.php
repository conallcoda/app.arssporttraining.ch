<?php

namespace App\QueryBuilders;

use Coda\Cms\QueryBuilder\DefaultQueryBuilder;
use Coda\Cms\QueryBuilder\QueryExpressionFilter;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class UserQueryBuilder extends DefaultQueryBuilder
{
    /** @return array<int, string|AllowedSort> */
    public function getDefinedSorts(): array
    {
        return $this->resolveSorts([
            'id',
            'personName => surname.concat("forename")',
            'updatedAt',
            'coach => owner.surname.concat("forename")',
        ]);
    }

    /** @return array<int, string|AllowedSort> */
    public function getDefaultSorts(): array
    {
        return $this->resolveSorts([
            'personName => surname.concat("forename") asc',
        ]);
    }

    /** @return array<int, string|AllowedFilter> */
    public function getDefinedFilters(): array
    {
        return [
            QueryExpressionFilter::make('search', 'forename contains $value or surname contains $value', [
                'fields' => ['forename', 'surname'],
            ]),
        ];
    }
}
