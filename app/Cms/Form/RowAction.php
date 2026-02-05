<?php

namespace App\Cms\Form;

class RowAction
{
    public function __construct(
        public string $name,
        public string $label,
        public ?string $icon = null,
        public ?string $method = null,
        public ?string $variant = null,
    ) {}

    public static function make(string $name, string $label): static
    {
        return new static($name, $label);
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function method(string $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function variant(string $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function getMethod(): string
    {
        return $this->method ?? $this->name;
    }
}
