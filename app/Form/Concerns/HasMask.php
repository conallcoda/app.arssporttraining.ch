<?php

namespace App\Form\Concerns;

trait HasMask
{
    public ?string $mask = null;

    public function mask(string $mask): static
    {
        $this->mask = $mask;

        return $this;
    }
}
