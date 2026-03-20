<?php

namespace App\QueryBuilders;

use App\Support\Sorts\CategorySort;
use App\Support\Sorts\CoachSort;
use Coda\Cms\QueryBuilder\DefaultQueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class ExerciseQueryBuilder extends DefaultQueryBuilder
{
    /** @return array<int, string|\Spatie\QueryBuilder\AllowedSort> */
    public function getDefinedSorts(): array
    {
        return [
            'id',
            'name',
            'type',
            AllowedSort::field('updatedAt', 'updated_at'),
            CoachSort::make('coach', 'exercises'),
            CategorySort::make('category', 'category_id', 'exercises'),
        ];
    }

    /** @return array<int, string|\Spatie\QueryBuilder\AllowedFilter> */
    public function getDefinedFilters(): array
    {
        return [
            AllowedFilter::callback('search', function ($query, $value): void {
                $query->where(function ($subQuery) use ($value): void {
                    $subQuery->where('name', 'like', '%'.$value.'%')
                        ->orWhere('type', 'like', '%'.$value.'%');
                });
            }),
            AllowedFilter::exact('type'),
        ];
    }
}
