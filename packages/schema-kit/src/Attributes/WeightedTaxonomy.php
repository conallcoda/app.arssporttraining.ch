<?php

namespace Coda\SchemaKit\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class WeightedTaxonomy extends RelationshipField
{
    protected function relationshipType(): string
    {
        return 'weighted_taxonomy';
    }

    protected function multiple(): bool
    {
        return true;
    }
}
