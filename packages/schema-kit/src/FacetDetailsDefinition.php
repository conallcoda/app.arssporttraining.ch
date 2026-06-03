<?php

namespace Coda\SchemaKit;

final class FacetDetailsDefinition
{
    private ?string $fieldset = null;

    public static function make(): static
    {
        return new static;
    }

    public function fieldset(?string $fieldset): static
    {
        $this->fieldset = $fieldset;

        return $this;
    }

    public function getFieldset(): ?string
    {
        return $this->fieldset;
    }
}
