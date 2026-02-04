<?php

namespace App\Data\Form\Concerns;

trait HasSuffix
{
    public ?string $suffix = null;

    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }
}
