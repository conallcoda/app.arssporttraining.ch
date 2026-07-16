<?php

namespace Coda\SchemaKit\Attributes;

use Coda\SchemaKit\FieldDefinition;
use Coda\SchemaKit\Relationship;
use Coda\SchemaKit\RelationshipDefinition;

abstract class RelationshipField extends Field implements CreatesFieldDefinition
{
    public function __construct(
        private readonly string $targetEntity,
        ?string $label = null,
        ?string $attribute = null,
        ?string $help = null,
        ?bool $title = null,
        ?bool $modal = null,
        ?bool $readable = null,
        ?bool $writable = null,
        ?bool $formVisible = null,
        string|array|null $rules = null,
    ) {
        parent::__construct($label, $attribute, $help, $title, $modal, $readable, $writable, $formVisible, $rules);
    }

    public function createFieldDefinition(string $name): FieldDefinition
    {
        return Relationship::make($name);
    }

    public function apply(FieldDefinition $field): void
    {
        parent::apply($field);

        if (! $field instanceof RelationshipDefinition) {
            return;
        }

        $field
            ->to($this->targetEntity)
            ->relationshipType($this->relationshipType())
            ->multiple($this->multiple());
    }

    abstract protected function relationshipType(): string;

    protected function multiple(): bool
    {
        return false;
    }
}
