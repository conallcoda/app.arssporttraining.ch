<?php

namespace App\Cms\Display\Concerns;

trait HasPrefix
{
    public ?string $prefix = null;

    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }
}
