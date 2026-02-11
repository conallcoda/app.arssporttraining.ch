<?php

namespace App\Cms\Display\Concerns;

trait HasDisplayAttribute
{
    public string $displayAttribute = 'name';

    public function displayAttribute(string $attribute): static
    {
        $this->displayAttribute = $attribute;

        return $this;
    }
}
