<?php

namespace Coda\Cms\Layouts;

use Closure;

final class TableLayout
{
    private array $facets = [];

    private array $columns = [];

    private array $sortable = [];

    private array|Closure $filters = [];

    private ?array $defaultSort = null;

    public static function make(): static
    {
        return new static;
    }

    public function facets(array $facets): static
    {
        $this->facets = array_values($facets);

        return $this;
    }

    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function sortable(array $sortable): static
    {
        $this->sortable = array_values($sortable);

        return $this;
    }

    public function filters(array|Closure $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    public function defaultSort(string $field, string $direction = 'asc'): static
    {
        $this->defaultSort = [
            'field' => $field,
            'direction' => $direction,
        ];

        return $this;
    }

    public function getFacets(): array
    {
        return $this->facets;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getSortable(): array
    {
        return $this->sortable;
    }

    public function getFilters(): array|Closure
    {
        return $this->filters;
    }

    public function getDefaultSort(): ?array
    {
        return $this->defaultSort;
    }
}
