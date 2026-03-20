<?php

namespace App\QueryBuilders;

use App\Support\Sorts\CategorySort;
use App\Support\Sorts\CoachSort;
use Coda\Cms\QueryBuilder\DefaultQueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class ExerciseProgramQueryBuilder extends DefaultQueryBuilder
{
    /** @return array<int, string|\Spatie\QueryBuilder\AllowedSort> */
    public function getDefinedSorts(): array
    {
        return [
            'id',
            'name',
            AllowedSort::field('updatedAt', 'updated_at'),
            CoachSort::make('coach', 'exercise_programs'),
            CategorySort::make('category', 'exercise_category_id', 'exercise_programs'),
        ];
    }

    /** @return array<int, string|\Spatie\QueryBuilder\AllowedFilter> */
    public function getDefinedFilters(): array
    {
        return [
            AllowedFilter::callback('search', function ($query, $value): void {
                $query->where('exercise_programs.name', 'like', '%'.$value.'%');
            }),
            AllowedFilter::exact('category', 'exercise_category_id'),
        ];
    }
}
