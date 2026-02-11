<?php

namespace App\Cms\Models\Concerns;

use App\Cms\QueryBuilder\DefaultQueryBuilder;

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
}
