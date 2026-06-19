<?php

namespace Coda\SchemaKit;

use Closure;

final class FacetFormDefinition
{
    private ?string $label = null;

    private ?string $prefix = null;

    private ?string $view = null;

    private array $viewData = [];

    private array|Closure|null $fields = null;

    private ?string $tab = null;

    private ?array $layout = null;

    private ?string $fieldset = null;

    public static function make(): static
    {
        return new static;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function prefix(?string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function view(?string $view): static
    {
        $this->view = $view;

        return $this;
    }

    public function viewData(array $viewData): static
    {
        $this->viewData = $viewData;

        return $this;
    }

    public function fields(array|Closure|null $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    public function tab(?string $tab): static
    {
        $this->tab = $tab;

        return $this;
    }

    public function layout(?array $layout): static
    {
        $this->layout = $layout;

        return $this;
    }

    public function fieldset(?string $fieldset): static
    {
        $this->fieldset = $fieldset;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function getView(): ?string
    {
        return $this->view;
    }

    public function getViewData(): array
    {
        return $this->viewData;
    }

    public function getFields(): array|Closure|null
    {
        return $this->fields;
    }

    public function getTab(): ?string
    {
        return $this->tab;
    }

    public function getLayout(): ?array
    {
        return $this->layout;
    }

    public function getFieldset(): ?string
    {
        return $this->fieldset;
    }

    public function __clone()
    {
        $this->viewData = [...$this->viewData];
        $this->layout = is_array($this->layout) ? [...$this->layout] : null;
    }
}
