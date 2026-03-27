<?php

namespace Coda\Cms\QueryBuilder;

use Spatie\QueryBuilder\AllowedFilter;

class QueryExpressionFilter
{
    public static function make(string $name, string $expression, array $whitelist): AllowedFilter
    {
        $walker = new QueryExpressionWalker;

        return AllowedFilter::callback($name, function ($query, $value) use ($walker, $expression, $whitelist): void {
            $walker->apply($query, $expression, $whitelist, $value);
        });
    }
}
