<?php

namespace Coda\Cms\Layout\Concerns;

trait HasSchema
{
    public array $schema = [];

    public function schema(array $schema): static
    {
        $this->schema = array_values($schema);

        return $this;
    }
}
