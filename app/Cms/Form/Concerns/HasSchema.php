<?php

namespace App\Cms\Form\Concerns;

trait HasSchema
{
    public array $schema = [];

    public function schema(array $schema): static
    {
        $this->schema = $schema;

        return $this;
    }
}
