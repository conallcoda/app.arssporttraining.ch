<?php

namespace App\Support;

use Coda\Cms\Display\IndexTab;
use Illuminate\Contracts\Database\Eloquent\Builder;

class OwnershipTabs
{
    public function __construct(
        protected string $entityName,
    ) {}

    public static function make(string $entityName): self
    {
        return new self($entityName);
    }

    /** @return IndexTab[] */
    public function toArray(): array
    {
        return [
            IndexTab::make('mine', __("My {$this->entityName}"))
                ->query(fn (Builder $query) => $query->where(
                    $query->getModel()->qualifyColumn('owner_id'),
                    auth()->id(),
                )),
            IndexTab::make('all', __("All {$this->entityName}")),
        ];
    }
}
