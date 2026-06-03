<?php

namespace Coda\Cms\Layouts;

final class FacetLayout
{
    private ?string $label = null;

    private ?array $fields = null;

    private ?string $view = null;

    private array $viewData = [];

    private ?string $tab = null;

    private ?string $fieldset = null;

    private ?array $layout = null;

    public function __construct(
        private readonly string $name,
    ) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function fields(?array $fields): static
    {
        $this->fields = $fields;

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

    public function tab(?string $tab): static
    {
        $this->tab = $tab;

        return $this;
    }

    public function fieldset(?string $fieldset): static
    {
        $this->fieldset = $fieldset;

        return $this;
    }

    public function layout(?array $layout): static
    {
        $this->layout = $layout;

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getFields(): ?array
    {
        return $this->fields;
    }

    public function getView(): ?string
    {
        return $this->view;
    }

    public function getViewData(): array
    {
        return $this->viewData;
    }

    public function getTab(): ?string
    {
        return $this->tab;
    }

    public function getFieldset(): ?string
    {
        return $this->fieldset;
    }

    public function getLayout(): ?array
    {
        return $this->layout;
    }
}
