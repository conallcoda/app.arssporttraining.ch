<?php

namespace App\Cms\Display\Concerns;

trait HasSortable
{
    public bool $sortable = false;

    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }
}
