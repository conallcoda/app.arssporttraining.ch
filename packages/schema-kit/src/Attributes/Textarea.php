<?php

namespace Coda\SchemaKit\Attributes;

use Attribute;
use Coda\SchemaKit\FieldDefinition;
use Coda\SchemaKit\TextareaInput;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Textarea extends Field
{
    public function apply(FieldDefinition $field): void
    {
        parent::apply($field);
        $field->input(TextareaInput::make());
    }
}
