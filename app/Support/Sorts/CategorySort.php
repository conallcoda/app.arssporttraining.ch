<?php

namespace App\Support\Sorts;

use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedSort;

class CategorySort
{
    public static function make(string $name = 'category', string $foreignKey = 'category_id', ?string $tableName = null): AllowedSort
    {
        return AllowedSort::callback($name, function ($query, bool $descending) use ($foreignKey, $tableName): void {
            $fkColumn = $tableName ? "{$tableName}.{$foreignKey}" : $foreignKey;
            $direction = $descending ? 'desc' : 'asc';

            $query->orderBy(
                DB::raw("(SELECT t.name FROM tags t WHERE t.id = {$fkColumn})"),
                $direction,
            );
        });
    }
}
