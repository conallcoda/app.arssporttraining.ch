<?php

namespace Coda\Cms\Form\Concerns;

trait HasUppercase
{
    public bool $uppercase = false;

    public function uppercase(bool $uppercase = true): static
    {
        $this->uppercase = $uppercase;

        return $this;
    }
}
