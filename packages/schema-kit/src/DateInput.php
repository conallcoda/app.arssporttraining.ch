<?php

namespace Coda\SchemaKit;

final class DateInput extends InputDefinition
{
    public static function make(): static
    {
        return new static;
    }

    public function kind(): string
    {
        return 'date';
    }
}
