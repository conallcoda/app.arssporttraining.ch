<?php

namespace Coda\SchemaKit;

final class TextareaInput extends InputDefinition
{
    private ?int $rows = null;

    public static function make(): static
    {
        return new static;
    }

    public function rows(?int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    public function getRows(): ?int
    {
        return $this->rows;
    }

    public function kind(): string
    {
        return 'textarea';
    }
}
