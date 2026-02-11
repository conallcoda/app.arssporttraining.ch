<?php

namespace App\Cms\Display\Concerns;

trait HasLabel
{
    public ?string $label = null;

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getDisplayLabel(): string
    {
        return $this->label ?? ucfirst(str_replace('_', ' ', $this->field));
    }
}
