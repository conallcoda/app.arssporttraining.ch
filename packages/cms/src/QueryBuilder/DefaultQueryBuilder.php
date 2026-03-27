<?php

namespace Coda\Cms\QueryBuilder;

use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class DefaultQueryBuilder extends QueryBuilder
{
    /** @param array<int, string|AllowedSort> $definitions */
    protected function resolveSorts(array $definitions): array
    {
        $model = method_exists($this, 'getModel') ? $this->getModel() : null;

        return (new SortDefinitionResolver($model))->resolve($definitions);
    }

    /** @return array<int, string|AllowedSort> */
    public function getDefinedSorts(): array
    {
        return [];
    }

    /** @return array<int, string|AllowedSort> */
    public function getDefaultSorts(): array
    {
        return [];
    }

    /** @return array<int, string|AllowedFilter> */
    public function getDefinedFilters(): array
    {
        return [];
    }
}
