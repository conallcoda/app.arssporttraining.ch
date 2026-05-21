<?php

namespace Coda\Cms\Display\Concerns;

trait HasTitle
{
    public bool $isTitle = false;

    public function title(): static
    {
        $this->isTitle = true;

        return $this;
    }

    public function isTitleField(): bool
    {
        return $this->isTitle;
    }
}
