<?php

namespace Coda\SchemaKit;

final class TextInput extends InputDefinition
{
    public static function make(): static
    {
        return new static;
    }

    public function kind(): string
    {
        return 'text';
    }
}
