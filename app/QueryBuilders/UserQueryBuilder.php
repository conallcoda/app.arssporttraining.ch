<?php

namespace App\QueryBuilders;

use App\Support\Sorts\CoachSort;
use Coda\Cms\QueryBuilder\DefaultQueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Enums\SortDirection;

class UserQueryBuilder extends DefaultQueryBuilder
{
    /** @return array<int, string|\Spatie\QueryBuilder\AllowedSort> */
    public function getDefinedSorts(): array
    {
        return [
            'id',
            AllowedSort::callback('personName', function ($query, bool $descending): void {
                $direction = $descending ? 'desc' : 'asc';
                $query->orderBy('surname', $direction)
                    ->orderBy('forename', $direction);
            })->defaultDirection(SortDirection::ASCENDING),
            AllowedSort::field('updatedAt', 'updated_at'),
            CoachSort::make('coach', 'users'),
        ];
    }

    /** @return array<int, string|\Spatie\QueryBuilder\AllowedSort> */
    public function getDefaultSorts(): array
    {
        return [
            AllowedSort::callback('personName', function ($query, bool $descending): void {
                $direction = $descending ? 'desc' : 'asc';
                $query->orderBy('surname', $direction)
                    ->orderBy('forename', $direction);
            })->defaultDirection(SortDirection::ASCENDING),
        ];
    }

    /** @return array<int, string|\Spatie\QueryBuilder\AllowedFilter> */
    public function getDefinedFilters(): array
    {
        return [
            AllowedFilter::callback('search', function ($query, $value): void {
                $query->where(function ($subQuery) use ($value): void {
                    $subQuery->where('forename', 'like', '%'.$value.'%')
                        ->orWhere('surname', 'like', '%'.$value.'%');
                });
            }),
        ];
    }
}
