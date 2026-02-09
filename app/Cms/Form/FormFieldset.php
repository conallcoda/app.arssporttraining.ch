<?php

namespace App\Cms\Form;

use App\Cms\Form\Concerns\HasCondition;

class FormFieldset
{
    use HasCondition;

    public string $name;

    public string $label = '';

    public array $fields = [];

    public ?string $prefix = null;

    public bool $collapsible = true;

    public array $hiddenFieldNames = [];

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
}
