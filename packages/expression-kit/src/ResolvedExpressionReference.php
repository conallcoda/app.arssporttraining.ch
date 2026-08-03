<?php

namespace Coda\ExpressionKit;

use Illuminate\Database\Query\Builder as BaseQueryBuilder;

final class ResolvedExpressionReference
{
    private function __construct(
        public readonly string $type,
        public readonly ?string $column = null,
        public readonly ?string $relationship = null,
        public readonly ?BaseQueryBuilder $query = null,
        public readonly ?string $expression = null,
        public readonly ?string $path = null,
    ) {}

    public static function field(string $column): self
    {
        return new self(type: 'field', column: $column);
    }

    public static function relationship(string $relationship, string $column = 'id'): self
    {
        return new self(type: 'relationship', relationship: $relationship, column: $column);
    }

    public static function subquery(BaseQueryBuilder $query): self
    {
        return new self(type: 'subquery', query: $query);
    }

    public static function expression(string $expression, ?string $path = null): self
    {
        return new self(type: 'expression', expression: $expression, path: $path);
    }

    public function isField(): bool
    {
        return $this->type === 'field' || $this->type === 'column';
    }

    public function isRelationship(): bool
    {
        return $this->type === 'relationship';
    }

    public function isSubquery(): bool
    {
        return $this->type === 'subquery';
    }

    public function isExpression(): bool
    {
        return $this->type === 'expression';
    }
}
