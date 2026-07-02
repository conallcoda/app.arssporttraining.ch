<?php

namespace Coda\Cms\Layouts;

final class TreeLayout
{
    private array $facets = [];

    private ?string $children = null;

    private ?string $parentKey = null;

    private ?string $titleField = null;

    private bool $reorderable = false;

    private bool $creatable = false;

    private bool $editable = false;

    public static function make(): static
    {
        return new static;
    }

    public function facets(array $facets): static
    {
        $this->facets = array_values($facets);

        return $this;
    }

    public function children(string $children): static
    {
        $this->children = $children;

        return $this;
    }

    public function parentKey(string $parentKey): static
    {
        $this->parentKey = $parentKey;

        return $this;
    }

    public function titleField(string $titleField): static
    {
        $this->titleField = $titleField;

        return $this;
    }

    public function reorderable(bool $reorderable = true): static
    {
        $this->reorderable = $reorderable;

        return $this;
    }

    public function creatable(bool $creatable = true): static
    {
        $this->creatable = $creatable;

        return $this;
    }

    public function editable(bool $editable = true): static
    {
        $this->editable = $editable;

        return $this;
    }
}
