<?php

namespace Coda\Cms\QueryBuilder;

use Spatie\QueryBuilder\AllowedFilter;

class QueryExpressionFilter
{
    public static function make(string $name, string $expression, array $whitelist): AllowedFilter
    {
        $resolver = new WhitelistFieldResolver($whitelist, str_contains($expression, '$value'));
        $walker = new QueryExpressionWalker($resolver);

        return AllowedFilter::callback($name, function ($query, $value) use ($walker, $expression): void {
            $walker->apply($query, $expression, $value);
        });
    }
}
