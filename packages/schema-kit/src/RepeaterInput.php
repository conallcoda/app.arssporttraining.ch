<?php

namespace Coda\SchemaKit;

final class RepeaterInput extends InputDefinition
{
    private array $schema = [];

    public static function make(): static
    {
        return new static;
    }

    public function schema(array $schema): static
    {
        $this->schema = $schema;

        return $this;
    }

    public function getSchema(): array
    {
        return $this->schema;
    }

    public function kind(): string
    {
        return 'repeater';
    }
}
