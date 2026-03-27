<?php

namespace App\QueryBuilders;

use Coda\Cms\QueryBuilder\DefaultQueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class MetricSubmissionQueryBuilder extends DefaultQueryBuilder
{
    /** @return array<int, string|AllowedSort> */
    public function getDefinedSorts(): array
    {
        return $this->resolveSorts([
            'recorded_at => recorded_at, created_at desc',
        ]);
    }

    /** @return array<int, string|AllowedSort> */
    public function getDefaultSorts(): array
    {
        return $this->getDefinedSorts();
    }

    /** @return array<int, string|AllowedFilter> */
    public function getDefinedFilters(): array
    {
        return [];
    }
}
