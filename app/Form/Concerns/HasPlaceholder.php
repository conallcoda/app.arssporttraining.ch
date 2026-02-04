<?php

namespace App\Form\Concerns;

trait HasPlaceholder
{
    public ?string $placeholder = null;

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }
}
