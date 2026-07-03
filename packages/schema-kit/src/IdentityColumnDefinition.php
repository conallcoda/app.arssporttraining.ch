<?php

namespace Coda\SchemaKit;

final class IdentityColumnDefinition extends TableColumnDefinition
{
    public static function make(?string $label = null): static
    {
        return (new static)->label($label);
    }

    public function type(): string
    {
        return 'identity';
    }
}
