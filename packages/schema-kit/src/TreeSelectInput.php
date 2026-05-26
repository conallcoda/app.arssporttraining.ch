<?php

namespace Coda\SchemaKit;

use Closure;

final class TreeSelectInput extends InputDefinition
{
    private array|Closure|null $options = null;

    private bool $searchable = false;

    private bool $clearable = false;

    private bool $multiple = false;

    private bool $excludeRoot = false;

    private bool $leafOnly = false;

    public static function make(): static
    {
        return new static;
    }

    public function options(array|Closure|null $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function clearable(bool $clearable = true): static
    {
        $this->clearable = $clearable;

        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function excludeRoot(bool $excludeRoot = true): static
    {
        $this->excludeRoot = $excludeRoot;

        return $this;
    }

    public function leafOnly(bool $leafOnly = true): static
    {
        $this->leafOnly = $leafOnly;

        return $this;
    }

    public function getOptions(): array|Closure|null
    {
        return $this->options;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isClearable(): bool
    {
        return $this->clearable;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function isExcludeRoot(): bool
    {
        return $this->excludeRoot;
    }

    public function isLeafOnly(): bool
    {
        return $this->leafOnly;
    }

    public function kind(): string
    {
        return 'tree_select';
    }
}
