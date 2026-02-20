<?php

namespace App\QueryBuilders;

use Coda\Cms\QueryBuilder\DefaultQueryBuilder;
use Spatie\QueryBuilder\AllowedSort;

class ExerciseExternalQueryBuilder extends DefaultQueryBuilder
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
}
