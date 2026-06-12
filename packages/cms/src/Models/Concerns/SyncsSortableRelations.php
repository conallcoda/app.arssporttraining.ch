<?php

namespace Coda\Cms\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait SyncsSortableRelations
{
    public function syncSortableRelation(BelongsToMany $relation, array $items, string $idKey = 'id', string $sortKey = 'sort'): void
    {
        $syncData = collect($items)
            ->filter(fn ($item) => ! empty($item[$idKey]))
            ->values()
            ->mapWithKeys(fn ($item, $index) => [
                $item[$idKey] => [$sortKey => $item[$sortKey] ?? $index],
            ])
            ->all();

        $relation->sync($syncData);
    }
}
