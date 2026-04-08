<?php

namespace Coda\Cms\Form\Concerns;

trait HasMaxLength
{
    public ?int $maxLength = null;

    public function maxLength(int $maxLength): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }
}
