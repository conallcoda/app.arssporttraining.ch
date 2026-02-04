<?php

namespace App\Data\Form\Concerns;

trait HasSchema
{
    public array $schema = [];

    public function schema(array $schema): static
    {
        $this->schema = $schema;

        return $this;
    }
}
