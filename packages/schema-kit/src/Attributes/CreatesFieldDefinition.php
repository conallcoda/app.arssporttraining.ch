<?php

namespace Coda\SchemaKit\Attributes;

use Coda\SchemaKit\FieldDefinition;

interface CreatesFieldDefinition
{
    public function createFieldDefinition(string $name): FieldDefinition;
}
