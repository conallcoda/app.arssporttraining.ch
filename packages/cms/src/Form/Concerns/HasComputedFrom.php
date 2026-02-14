<?php

namespace Coda\Cms\Form\Concerns;

trait HasComputedFrom
{
    public array $computedFrom = [];

    public function computedFrom(array $fields): static
    {
        $this->computedFrom = $fields;

        return $this;
    }
}
