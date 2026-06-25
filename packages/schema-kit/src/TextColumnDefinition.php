<?php

namespace Coda\SchemaKit;

final class TextColumnDefinition extends TableColumnDefinition
{
    public static function make(string $field): static
    {
        return new static($field);
    }

    public function type(): string
    {
        return 'text';
    }
}
