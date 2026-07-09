<?php

namespace Coda\Cms\Display\Concerns;

trait HasSticky
{
    public bool $sticky = false;

    public function sticky(bool $sticky = true): static
    {
        $this->sticky = $sticky;

        return $this;
    }
}
