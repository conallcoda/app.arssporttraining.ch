<?php

namespace Coda\Cms\Form\Concerns;

trait HasUnique
{
    public bool $unique = false;

    public function unique(bool $unique = true): static
    {
        $this->unique = $unique;

        return $this;
    }
}
