<?php

namespace Coda\SchemaKit;

interface DefinesSchema
{
    /**
     * @return array{
     *     fields?: array<int, string|FieldDefinition>,
     *     relationships?: array<int, string|RelationshipDefinition>,
     *     computed?: array<int, string|ComputedDefinition>
     * }
     */
    public static function schema(): array;
}
