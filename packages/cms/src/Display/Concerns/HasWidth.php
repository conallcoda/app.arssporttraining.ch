<?php

namespace Coda\Cms\Display\Concerns;

trait HasWidth
{
    public string $width = 'w-auto';

    public function width(string $width): static
    {
        $this->width = $width;

        return $this;
    }
}
