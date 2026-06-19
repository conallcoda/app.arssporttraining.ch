<?php

namespace Coda\SchemaKit;

class RelationshipDefinition extends FieldDefinition
{
    private ?string $targetEntity = null;

    private ?string $relationshipType = null;

    private bool $multiple = false;

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function to(?string $targetEntity): static
    {
        $this->targetEntity = $targetEntity;

        return $this;
    }

    public function relationshipType(?string $relationshipType): static
    {
        $this->relationshipType = $relationshipType;

        return $this;
    }

    public function belongsTo(): static
    {
        return $this->relationshipType('belongs_to');
    }

    public function taxonomy(): static
    {
        return $this->relationshipType('taxonomy');
    }

    public function weightedTaxonomy(): static
    {
        return $this->relationshipType('weighted_taxonomy');
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function getTargetEntity(): ?string
    {
        return $this->targetEntity;
    }

    public function getRelationshipType(): ?string
    {
        return $this->relationshipType;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function definitionType(): string
    {
        return 'relationship';
    }
}
