<?php

namespace App\Form\Concerns;

use Illuminate\Support\Str;

trait HasLabel
{
    public ?string $label = null;

    public ?string $helpText = null;

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label ?? Str::headline($this->name);
    }

    public function helpText(string $helpText): static
    {
        $this->helpText = $helpText;

        return $this;
    }
}
