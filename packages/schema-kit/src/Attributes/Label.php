<?php

namespace Coda\SchemaKit\Attributes;

use Attribute;
use Coda\SchemaKit\FieldDefinition;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class Label implements AppliesToFieldDefinition
{
    public function __construct(
        private readonly string $text,
    ) {}

    public function apply(FieldDefinition $field): void
    {
        $field->label($this->text);
    }
}
