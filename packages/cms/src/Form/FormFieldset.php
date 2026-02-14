<?php

namespace Coda\Cms\Form;

use Coda\Cms\Form\Concerns\HasCondition;

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
}
