<?php

namespace App\Data\Form\Concerns;

trait HasUnique
{
    public bool $unique = false;

    public function unique(bool $unique = true): static
    {
        $this->unique = $unique;

        return $this;
    }
}
