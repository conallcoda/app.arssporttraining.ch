<?php

namespace App\Cms\Display\Concerns;

trait HasModal
{
    public ?string $modalField = null;

    public function modal(?string $fieldName = null): static
    {
        $this->modalField = $fieldName ?? $this->field;

        return $this;
    }
}
