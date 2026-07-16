<?php

namespace Coda\FormKit;

use Coda\FormKit\Concerns\HasCondition;

class FormFieldset
{
    use HasCondition;

    public string $name;

    public string $label = '';

    public array $fields = [];

    public ?string $prefix = null;

    public bool $collapsible = true;

    public array $hiddenFieldNames = [];

    public ?string $view = null;

    public array $viewData = [];

    public ?array $layout = null;

    public array $rows = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    public function prefix(?string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function collapsible(bool $collapsible = true): static
    {
        $this->collapsible = $collapsible;

        return $this;
    }

    public function view(string $view): static
    {
        $this->view = $view;

        return $this;
    }

    public function viewData(array $data): static
    {
        $this->viewData = $data;

        return $this;
    }

    public function layout(?array $layout): static
    {
        $this->layout = $layout;

        return $this;
    }

    public function rows(array $rows): static
    {
        $this->rows = $rows;

        return $this;
    }
}
