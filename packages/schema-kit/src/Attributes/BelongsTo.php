<?php

namespace Coda\SchemaKit\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class BelongsTo extends RelationshipField
{
    protected function relationshipType(): string
    {
        return 'belongs_to';
    }
}
