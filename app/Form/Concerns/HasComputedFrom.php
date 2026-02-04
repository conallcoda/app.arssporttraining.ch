<?php

namespace App\Form\Concerns;

trait HasComputedFrom
{
    public array $computedFrom = [];

    public function computedFrom(array $fields): static
    {
        $this->computedFrom = $fields;

        return $this;
    }
}
