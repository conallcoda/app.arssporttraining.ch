<?php

namespace Coda\SchemaKit\Attributes;

use Attribute;
use Coda\SchemaKit\FieldDefinition;
use Coda\SchemaKit\UrlInput;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Url extends Field
{
    public function apply(FieldDefinition $field): void
    {
        parent::apply($field);
        $field->input(UrlInput::make()->placeholder('https://'));
    }
}
