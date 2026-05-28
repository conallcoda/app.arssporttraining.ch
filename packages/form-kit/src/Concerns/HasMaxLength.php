<?php

namespace Coda\FormKit\Concerns;

trait HasMaxLength
{
    public ?int $maxLength = null;

    public function maxLength(int $maxLength): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }
}
