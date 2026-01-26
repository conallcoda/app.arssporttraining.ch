<?php

namespace App\Data\Form;

class TableColumn
{
    public function __construct(
        public string $field,
        public string $type = 'text',
        public ?string $label = null,
        public string $width = 'w-auto',
        public bool $editable = false,
        public ?string $suffix = null,
        public ?float $step = null,
        public ?float $min = null,
        public ?float $max = null,
        public string $displayAttribute = 'name',
    ) {}

    public static function text(string $field): static
    {
        return new static($field, 'text');
    }

    public static function number(string $field): static
    {
        return new static($field, 'number');
    }

    public static function relationship(string $field): static
    {
        return new static($field, 'relationship');
    }

    public function displayAttribute(string $attribute): static
    {
        $this->displayAttribute = $attribute;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function width(string $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function inline(bool $inline = true): static
    {
        $this->editable = $inline;

        return $this;
    }

    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function step(float $step): static
    {
        $this->step = $step;

        return $this;
    }

    public function min(float $min): static
    {
        $this->min = $min;

        return $this;
    }

    public function max(float $max): static
    {
        $this->max = $max;

        return $this;
    }

    public function getDisplayLabel(): string
    {
        return $this->label ?? ucfirst(str_replace('_', ' ', $this->field));
    }
}
