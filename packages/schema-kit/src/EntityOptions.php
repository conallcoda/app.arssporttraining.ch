<?php

namespace Coda\SchemaKit;

use Closure;

final class EntityOptions
{
    private ?string $scopeEntityName = null;

    /** @var array<int, string> */
    private array $with = [];

    /** @var list<array{column: string, direction: string}> */
    private array $orderBy = [];

    private ?Closure $queryUsing = null;

    private ?Closure $labelUsing = null;

    private ?Closure $valueUsing = null;

    private string $parentAttribute = 'parent_id';

    private function __construct(
        private readonly string $entityName,
    ) {}

    public static function make(string $entityName): static
    {
        return new static($entityName);
    }

    public function entityName(): string
    {
        return $this->entityName;
    }

    public function scopeEntity(string $entityName): static
    {
        $this->scopeEntityName = $entityName;

        return $this;
    }

    /**
     * @param  array<int, string>  $relations
     */
    public function with(array $relations): static
    {
        $this->with = array_values(array_filter($relations, static fn (mixed $relation): bool => is_string($relation) && $relation !== ''));

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $this->orderBy[] = ['column' => $column, 'direction' => $direction];

        return $this;
    }

    public function queryUsing(?Closure $queryUsing): static
    {
        $this->queryUsing = $queryUsing;

        return $this;
    }

    public function labelUsing(?Closure $labelUsing): static
    {
        $this->labelUsing = $labelUsing;

        return $this;
    }

    public function valueUsing(?Closure $valueUsing): static
    {
        $this->valueUsing = $valueUsing;

        return $this;
    }

    public function parentAttribute(string $parentAttribute): static
    {
        $this->parentAttribute = $parentAttribute;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getWith(): array
    {
        return $this->with;
    }

    public function scopeEntityName(): string
    {
        return $this->scopeEntityName ?? $this->entityName;
    }

    /**
     * @return list<array{column: string, direction: string}>
     */
    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    public function getQueryUsing(): ?Closure
    {
        return $this->queryUsing;
    }

    public function getLabelUsing(): ?Closure
    {
        return $this->labelUsing;
    }

    public function getValueUsing(): ?Closure
    {
        return $this->valueUsing;
    }

    public function getParentAttribute(): string
    {
        return $this->parentAttribute;
    }
}
