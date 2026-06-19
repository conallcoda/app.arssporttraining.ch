<?php

namespace Coda\SchemaKit;

final class UrlInput extends InputDefinition
{
    public static function make(): static
    {
        return new static;
    }

    public function kind(): string
    {
        return 'url';
    }
}
