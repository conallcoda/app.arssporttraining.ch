<?php

namespace Coda\Cms\Display;

use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\Pagination\Classic;
use Coda\FormKit\Field;

class Table
{
    protected array $columns = [];

    protected ?array $cardFields = null;

    protected string $defaultView = 'table';

    protected ?Pagination $paginationConfig = null;

    protected array $actions = [];

    protected array $sortableFields = [];

    protected ?string $defaultSortField = null;

    protected string $defaultSortDirection = 'asc';

    /** @var TableFilter[] */
    protected array $filters = [];

    protected int $limit = 10;

    public static function make(): static
    {
        return new static;
    }

    public function columns(array $columns): static
    {
        $this->columns = $columns;

        if ($this->defaultSortField === null) {
            foreach ($columns as $column) {
                if ($column instanceof Id) {
                    $this->defaultSortField = $column->field;
                    break;
                }
            }
        }

        return $this;
    }

    public function defaultSort(string $field, string $direction = 'asc'): static
    {
        $this->defaultSortField = $field;
        $this->defaultSortDirection = $direction;

        return $this;
    }

    public function actions(array $actions): static
    {
        $this->actions = $actions;

        return $this;
    }

    public function sortable(array $fields): static
    {
        $this->sortableFields = $fields;

        foreach ($this->columns as $column) {
            if (in_array($column->field, $fields, true) || in_array($column->sortField, $fields, true)) {
                $column->sortable();
            }
        }

        return $this;
    }

    /** @param TableFilter[] $filters */
    public function filters(array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function cards(array $fields): static
    {
        $this->cardFields = $fields;

        return $this;
    }

    public function getCards(): array
    {
        return $this->cardFields ?? [];
    }

    public function hasCards(): bool
    {
        return $this->cardFields !== null;
    }

    public function defaultView(string $view): static
    {
        $this->defaultView = $view;

        return $this;
    }

    public function getDefaultView(): string
    {
        return $this->defaultView;
    }

    public function pagination(Pagination $pagination): static
    {
        $this->paginationConfig = $pagination;

        return $this;
    }

    public function getPagination(): Pagination
    {
        return $this->paginationConfig ?? (new Classic)->perPage($this->limit);
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    public function getSortableFields(): array
    {
        return $this->sortableFields;
    }

    public function hasActions(): bool
    {
        return count($this->actions) > 0;
    }

    /** @return TableFilter[] */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function hasFilters(): bool
    {
        return count($this->filters) > 0;
    }

    /** @return Field[] */
    public function getFilterFields(): array
    {
        return collect($this->filters)
            ->filter(fn (TableFilter $filter) => $filter->hasField())
            ->map(fn (TableFilter $filter) => $filter->getField())
            ->values()
            ->all();
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getDefaultSortField(): ?string
    {
        return $this->defaultSortField;
    }

    public function getDefaultSortDirection(): string
    {
        return $this->defaultSortDirection;
    }

    public function hasDefaultSort(): bool
    {
        return $this->defaultSortField !== null;
    }

    public function getDefaultSortString(): string
    {
        if (! $this->defaultSortField) {
            return '';
        }

        return $this->defaultSortDirection === 'desc'
            ? "-{$this->defaultSortField}"
            : $this->defaultSortField;
    }
}
