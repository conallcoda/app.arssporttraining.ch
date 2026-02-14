<?php

namespace Coda\Cms\Form\Concerns;

trait HasTicks
{
    public array $ticks = [];

    public function ticks(array $ticks): static
    {
        $this->ticks = $ticks;

        return $this;
    }
}
