<?php

namespace Coda\SchemaKit;

use Closure;

abstract class TableColumnDefinition
{
    protected ?string $field = null;

    protected ?string $label = null;

    protected ?string $sortAs = null;

    protected ?string $help = null;

    protected ?string $suffix = null;

    protected bool $modal = false;

    protected bool $title = false;

    protected ?Closure $source = null;

    public function __construct(?string $field = null)
    {
        $this->field = $field;
    }

    public function field(?string $field): static
    {
        $this->field = $field;

        return $this;
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function sortAs(?string $sortAs): static
    {
        $this->sortAs = $sortAs;

        return $this;
    }

    public function help(?string $help): static
    {
        $this->help = $help;

        return $this;
    }

    public function suffix(?string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function modal(bool $modal = true): static
    {
        $this->modal = $modal;

        return $this;
    }

    public function title(bool $title = true): static
    {
        $this->title = $title;

        return $this;
    }

    public function source(?Closure $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getField(): ?string
    {
        return $this->field;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getSortAs(): ?string
    {
        return $this->sortAs;
    }

    public function getHelp(): ?string
    {
        return $this->help;
    }

    public function getSuffix(): ?string
    {
        return $this->suffix;
    }

    public function isModal(): bool
    {
        return $this->modal;
    }

    public function isTitle(): bool
    {
        return $this->title;
    }

    public function getSource(): ?Closure
    {
        return $this->source;
    }

    abstract public function type(): string;
}
