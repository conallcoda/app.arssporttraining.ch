<?php

namespace App\QueryBuilders;

use Coda\Cms\QueryBuilder\DefaultQueryBuilder;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Enums\SortDirection;

class MetricSubmissionQueryBuilder extends DefaultQueryBuilder
{
    /** @return array<int, string|\Spatie\QueryBuilder\AllowedSort> */
    public function getDefinedSorts(): array
    {
        return [
            AllowedSort::callback('recorded_at', function ($query, bool $descending): void {
                $direction = $descending ? 'desc' : 'asc';
                $query->orderBy('recorded_at', $direction)
                    ->orderBy('created_at', $direction);
            })->defaultDirection(SortDirection::DESCENDING),
        ];
    }

    /** @return array<int, string|\Spatie\QueryBuilder\AllowedSort> */
    public function getDefaultSorts(): array
    {
        return $this->getDefinedSorts();
    }

    /** @return array<int, string|\Spatie\QueryBuilder\AllowedFilter> */
    public function getDefinedFilters(): array
    {
        return [];
    }
}
