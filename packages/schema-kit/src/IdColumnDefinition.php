<?php

namespace Coda\SchemaKit;

final class IdColumnDefinition extends TableColumnDefinition
{
    public static function make(): static
    {
        return new static;
    }

    public function type(): string
    {
        return 'id';
    }
}
