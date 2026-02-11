<?php

namespace App\QueryBuilders;

use App\Cms\QueryBuilder\DefaultQueryBuilder;

class ExerciseQueryBuilder extends DefaultQueryBuilder
{
    /** @return array<int, string|\Spatie\QueryBuilder\AllowedSort> */
    public function getDefinedSorts(): array
    {
        return [
            'id',
            'name',
            'type',
        ];
    }
}
