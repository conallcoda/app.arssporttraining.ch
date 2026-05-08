<?php

namespace Coda\FormKit\Concerns;

trait HasPlaceholder
{
    public ?string $placeholder = null;

    protected bool $placeholderExplicitlySet = false;

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;
        $this->placeholderExplicitlySet = true;

        return $this;
    }

    public function getPlaceholder(): ?string
    {
        if ($this->placeholderExplicitlySet) {
            return $this->placeholder;
        }

        return $this->placeholder ?? $this->getLabel();
    }
}
