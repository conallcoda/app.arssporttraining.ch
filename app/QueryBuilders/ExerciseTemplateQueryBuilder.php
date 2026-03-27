<?php

namespace App\QueryBuilders;

use Coda\Cms\QueryBuilder\DefaultQueryBuilder;
use Spatie\QueryBuilder\AllowedSort;

class ExerciseTemplateQueryBuilder extends DefaultQueryBuilder
{
    /** @return array<int, string|AllowedSort> */
    public function getDefinedSorts(): array
    {
        return $this->resolveSorts([
            'id',
            'name',
            'updatedAt',
        ]);
    }
}
