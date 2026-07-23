<?php

namespace Coda\SchemaKit\Attributes;

use Attribute;
use Coda\SchemaKit\FieldDefinition;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class AttributeName implements AppliesToFieldDefinition
{
    public function __construct(
        private readonly string $name,
    ) {}

    public function apply(FieldDefinition $field): void
    {
        $field->attribute($this->name);
    }
}
