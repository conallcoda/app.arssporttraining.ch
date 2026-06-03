<?php

namespace Coda\SchemaKit;

use Closure;

class ViewDefinition
{
    private ?string $label = null;

    private array $facetNames = [];

    private ?TableViewDefinition $table = null;

    private ?DetailsViewDefinition $details = null;

    private ?string $formModalWidth = null;

    private array $meta = [];

    public function __construct(
        private readonly string $name,
    ) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function facets(array $facetNames): static
    {
        $this->facetNames = array_values($facetNames);

        return $this;
    }

    public function appendFacets(array $facetNames): static
    {
        $this->facetNames = array_values(array_unique([
            ...$this->facetNames,
            ...$facetNames,
        ]));

        return $this;
    }

    public function show(array $fieldNames): static
    {
        return $this->setMeta('show_fields', array_values($fieldNames));
    }

    public function table(TableViewDefinition|callable|null $configure = null): TableViewDefinition|static
    {
        if ($configure instanceof TableViewDefinition) {
            $this->table = $configure;

            return $this;
        }

        $this->table ??= new TableViewDefinition;

        if ($configure === null) {
            return $this->table;
        }

        $configure($this->table);

        return $this;
    }

    public function formTabs(array $tabs): static
    {
        return $this->setMeta('form_tabs', array_values($tabs));
    }

    public function formModalWidth(?string $formModalWidth): static
    {
        $this->formModalWidth = $formModalWidth;

        return $this;
    }

    public function details(DetailsViewDefinition|callable|null $configure = null): DetailsViewDefinition|static
    {
        if ($configure instanceof DetailsViewDefinition) {
            $this->details = $configure;

            return $this;
        }

        $this->details ??= new DetailsViewDefinition;

        if ($configure === null) {
            return $this->details;
        }

        $configure($this->details);

        return $this;
    }

    public function detailsTabs(array $tabs): static
    {
        return $this->setMeta('details_tabs', $tabs);
    }

    public function filters(array|Closure $filters): static
    {
        return $this->setMeta('filters', $filters);
    }

    public function sortable(array $sortable): static
    {
        return $this->setMeta('sortable', array_values($sortable));
    }

    public function defaultSort(string $field, string $direction = 'asc'): static
    {
        return $this->setMeta('default_sort', [
            'field' => $field,
            'direction' => $direction,
        ]);
    }

    public function setMeta(string $key, mixed $value): static
    {
        $this->meta[$key] = $value;

        return $this;
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    public function allMeta(): array
    {
        return $this->meta;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getFacetNames(): array
    {
        return $this->facetNames;
    }

    public function getTable(): ?TableViewDefinition
    {
        return $this->table;
    }

    public function getDetails(): ?DetailsViewDefinition
    {
        return $this->details;
    }

    public function getFormModalWidth(): ?string
    {
        return $this->formModalWidth;
    }
}
