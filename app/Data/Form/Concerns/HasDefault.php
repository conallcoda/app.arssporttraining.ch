<?php

namespace App\Data\Form\Concerns;

trait HasDefault
{
    public mixed $default = null;

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }
}
