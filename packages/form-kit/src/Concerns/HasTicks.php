<?php

namespace Coda\FormKit\Concerns;

trait HasTicks
{
    public array $ticks = [];

    public function ticks(array $ticks): static
    {
        $this->ticks = $ticks;

        return $this;
    }
}
