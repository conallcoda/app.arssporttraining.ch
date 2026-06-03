<?php

namespace Coda\Cms\QueryBuilder;

use Illuminate\Database\Eloquent\Builder;

class ConstrainedBaseQuerySealer
{
    public function seal(Builder $baseQuery): Builder
    {
        if (! $this->shouldSeal($baseQuery)) {
            return $baseQuery;
        }

        $model = $baseQuery->getModel();
        $table = $model->getTable();
        $sealed = $model->newQueryWithoutScopes()
            ->fromSub(clone $baseQuery->toBase(), $table)
            ->select("{$table}.*");

        $sealed->setEagerLoads($baseQuery->getEagerLoads());

        return $sealed;
    }

    protected function shouldSeal(Builder $baseQuery): bool
    {
        $query = $baseQuery->getQuery();
        $from = $query->from ?? null;

        if (! is_string($from) || $from !== $baseQuery->getModel()->getTable()) {
            return false;
        }

        return ! empty($query->wheres ?? [])
            || ! empty($query->groups ?? [])
            || ! empty($query->havings ?? [])
            || ! empty($query->unions ?? [])
            || $query->limit !== null
            || $query->offset !== null;
    }
}
