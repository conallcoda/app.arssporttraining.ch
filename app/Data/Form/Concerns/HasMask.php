<?php

namespace App\Data\Form\Concerns;

trait HasMask
{
    public ?string $mask = null;

    public function mask(string $mask): static
    {
        $this->mask = $mask;

        return $this;
    }
}
