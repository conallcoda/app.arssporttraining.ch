<?php

namespace Coda\SchemaKit\Attributes;

use Attribute;
use Coda\SchemaKit\FieldDefinition;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class Modal implements AppliesToFieldDefinition
{
    public function __construct(
        private readonly bool $enabled = true,
    ) {}

    public function apply(FieldDefinition $field): void
    {
        $field->modal($this->enabled);
    }
}
