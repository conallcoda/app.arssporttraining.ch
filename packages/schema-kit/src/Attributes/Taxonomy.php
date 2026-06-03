<?php

namespace Coda\SchemaKit\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Taxonomy extends RelationshipField
{
    protected function relationshipType(): string
    {
        return 'taxonomy';
    }
}
