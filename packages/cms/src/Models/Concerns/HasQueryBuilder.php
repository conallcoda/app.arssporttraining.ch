<?php

namespace Coda\Cms\Models\Concerns;

use Coda\Cms\QueryBuilder\DefaultQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait HasQueryBuilder
{
    public static function newEloquentQueryBuilder(): DefaultQueryBuilder
    {
        $concreteClass = 'App\\QueryBuilders\\'.class_basename(static::class).'QueryBuilder';

        if (class_exists($concreteClass)) {
            return $concreteClass::for(static::query());
        }

        return DefaultQueryBuilder::for(static::query());
    }

    public static function buildQueryBuilder(Builder $baseQuery, ?Request $request = null): DefaultQueryBuilder
    {
        $modelClass = get_class($baseQuery->getModel());
        $concreteClass = 'App\\QueryBuilders\\'.class_basename($modelClass).'QueryBuilder';

        if (class_exists($concreteClass)) {
            return $concreteClass::for($baseQuery, $request);
        }

        return DefaultQueryBuilder::for($baseQuery, $request);
    }
}
