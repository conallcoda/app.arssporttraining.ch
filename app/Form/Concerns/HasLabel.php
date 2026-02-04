<?php

namespace App\Form\Concerns;

trait HasLabel
{
    public ?string $label = null;

    public ?string $helpText = null;

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function helpText(string $helpText): static
    {
        $this->helpText = $helpText;

        return $this;
    }
}
