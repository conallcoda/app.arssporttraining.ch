<?php

namespace Coda\SchemaKit\Attributes;

use Coda\SchemaKit\FieldDefinition;

interface AppliesToFieldDefinition
{
    public function apply(FieldDefinition $field): void;
}
