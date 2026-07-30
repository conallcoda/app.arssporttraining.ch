<?php

namespace Coda\SchemaKit\Attributes;

use Attribute;
use Coda\SchemaKit\FieldDefinition;
use Coda\SchemaKit\TextInput;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Text extends Field
{
    public function apply(FieldDefinition $field): void
    {
        parent::apply($field);
        $field->input(TextInput::make());
    }
}
