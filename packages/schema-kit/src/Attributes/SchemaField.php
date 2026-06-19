<?php

namespace Coda\SchemaKit\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class SchemaField extends Field
{
}
