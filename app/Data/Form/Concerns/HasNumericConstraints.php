<?php

namespace App\Data\Form\Concerns;

trait HasNumericConstraints
{
    public ?float $min = null;

    public ?float $max = null;

    public ?float $step = null;

    public function min(float $min): static
    {
        $this->min = $min;

        return $this;
    }

    public function max(float $max): static
    {
        $this->max = $max;

        return $this;
    }

    public function step(float $step): static
    {
        $this->step = $step;

        return $this;
    }
}
