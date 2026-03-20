<?php

namespace App\Support\Sorts;

use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedSort;

class CoachSort
{
    public static function make(string $name = 'coach', ?string $tableName = null): AllowedSort
    {
        return AllowedSort::callback($name, function ($query, bool $descending) use ($tableName): void {
            $ownerColumn = $tableName ? "{$tableName}.owner_id" : 'owner_id';
            $direction = $descending ? 'desc' : 'asc';

            $query->orderBy(
                DB::raw("(SELECT CONCAT(u.surname, ' ', u.forename) FROM users u WHERE u.id = {$ownerColumn})"),
                $direction,
            );
        });
    }
}
