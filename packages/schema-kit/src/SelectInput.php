<?php

namespace Coda\SchemaKit;

use Closure;

final class SelectInput extends InputDefinition
{
    private array|Closure|null $options = null;

    private bool $searchable = false;

    private bool $clearable = false;

    private bool $multiple = false;

    private ?string $variant = null;

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

    public function variant(?string $variant): static
    {
        $this->variant = $variant;

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

    public function getVariant(): ?string
    {
        return $this->variant;
    }

    public function kind(): string
    {
        return 'select';
    }
}
