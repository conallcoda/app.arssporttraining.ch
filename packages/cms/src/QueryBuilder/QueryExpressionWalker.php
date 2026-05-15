<?php

namespace Coda\Cms\QueryBuilder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Node\ArrayNode;
use Symfony\Component\ExpressionLanguage\Node\BinaryNode;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;
use Symfony\Component\ExpressionLanguage\Node\NameNode;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\Node\UnaryNode;
use Symfony\Component\ExpressionLanguage\SyntaxError;

class QueryExpressionWalker
{
    private ExpressionLanguage $language;

    public function __construct(private FieldResolver $resolver)
    {
        $this->language = new ExpressionLanguage;
    }

    public function apply(Builder $query, string $expression, mixed $value = null): void
    {
        $normalised = str_replace('$value', 'value', $expression);

        $names = $this->resolver->names();

        if (str_contains($expression, '$value') && ! in_array('value', $names, true)) {
            $names[] = 'value';
        }

        try {
            $parsed = $this->language->parse($normalised, $names);
        } catch (SyntaxError $e) {
            throw new \InvalidArgumentException($e->getMessage(), 0, $e);
        }

        // Always wrap the parsed expression in a single grouped where clause so
        // later query constraints don't accidentally change boolean precedence.
        $query->where(function (Builder $q) use ($parsed, $value): void {
            $this->applyNode($q, $parsed->getNodes(), $value);
        });
    }

    private function applyNode(Builder $query, Node $node, mixed $value = null, string $boolean = 'and'): void
    {
        if ($node instanceof BinaryNode) {
            $this->applyBinaryNode($query, $node, $value, $boolean);

            return;
        }

        if ($node instanceof UnaryNode) {
            $this->applyUnaryNode($query, $node, $value, $boolean);

            return;
        }

        throw new \InvalidArgumentException(sprintf(
            'Unsupported node type "%s" in query expression.',
            get_class($node)
        ));
    }

    private function applyBinaryNode(Builder $query, BinaryNode $node, mixed $value, string $boolean): void
    {
        $operator = $node->attributes['operator'];

        if ($operator === 'and' || $operator === '&&') {
            $this->applyNode($query, $node->nodes['left'], $value, $boolean);
            $this->applyNode($query, $node->nodes['right'], $value, $boolean);

            return;
        }

        if ($operator === 'or' || $operator === '||') {
            $query->where(function (Builder $q) use ($node, $value): void {
                $this->applyNode($q, $node->nodes['left'], $value, 'and');
            }, boolean: $boolean);

            $query->where(function (Builder $q) use ($node, $value): void {
                $this->applyNode($q, $node->nodes['right'], $value, 'and');
            }, boolean: 'or');

            return;
        }

        $this->applyComparisonNode($query, $node, $value, $boolean);
    }

    private function applyComparisonNode(Builder $query, BinaryNode $node, mixed $value, string $boolean): void
    {
        $operator = $node->attributes['operator'];
        $left = $node->nodes['left'];
        $right = $node->nodes['right'];

        $fieldInfo = $this->resolver->resolve($left);
        $resolvedValue = $this->resolveValue($right, $value);

        $this->validateOperator($operator);

        if ($fieldInfo['type'] === 'subquery') {
            $this->applySubqueryCondition(
                $query,
                $fieldInfo['query'],
                $operator,
                $resolvedValue,
                $boolean
            );

            return;
        }

        if ($fieldInfo['type'] === 'relationship') {
            $this->applyRelationshipCondition(
                $query,
                $fieldInfo['relationship'],
                $fieldInfo['column'],
                $operator,
                $resolvedValue,
                $boolean
            );

            return;
        }

        $column = $fieldInfo['column'];

        match ($operator) {
            'in' => $query->whereIn($column, $resolvedValue, $boolean),
            'not in' => $query->whereNotIn($column, $resolvedValue, $boolean),
            'contains' => $query->where($column, 'like', '%'.$resolvedValue.'%', $boolean),
            'starts with' => $query->where($column, 'like', $resolvedValue.'%', $boolean),
            'ends with' => $query->where($column, 'like', '%'.$resolvedValue, $boolean),
            default => $query->where($column, $this->mapOperator($operator), $resolvedValue, $boolean),
        };
    }

    private function applySubqueryCondition(
        Builder $query,
        BaseQueryBuilder $subquery,
        string $operator,
        mixed $resolvedValue,
        string $boolean
    ): void {
        $query->where($subquery, $this->mapOperator($operator), $resolvedValue, $boolean);
    }

    private function applyRelationshipCondition(
        Builder $query,
        string $relationship,
        string $column,
        string $operator,
        mixed $value,
        string $boolean
    ): void {
        $method = $boolean === 'or' ? 'orWhereHas' : 'whereHas';

        $query->$method($relationship, function (Builder $q) use ($column, $operator, $value): void {
            match ($operator) {
                'in' => $q->whereIn($column, $value),
                'not in' => $q->whereNotIn($column, $value),
                'contains' => $q->where($column, 'like', '%'.$value.'%'),
                'starts with' => $q->where($column, 'like', $value.'%'),
                'ends with' => $q->where($column, 'like', '%'.$value),
                default => $q->where($column, $this->mapOperator($operator), $value),
            };
        });
    }

    private function applyUnaryNode(Builder $query, UnaryNode $node, mixed $value, string $boolean): void
    {
        $operator = $node->attributes['operator'];

        if ($operator === 'not' || $operator === '!') {
            $method = $boolean === 'or' ? 'orWhereNot' : 'whereNot';
            $query->$method(function (Builder $q) use ($node, $value): void {
                $this->applyNode($q, $node->nodes['node'], $value, 'and');
            });

            return;
        }

        throw new \InvalidArgumentException(sprintf(
            'Unsupported unary operator "%s" in query expression.',
            $operator
        ));
    }

    private function resolveValue(Node $node, mixed $runtimeValue): mixed
    {
        if ($node instanceof ConstantNode) {
            return $node->attributes['value'];
        }

        if ($node instanceof NameNode && $node->attributes['name'] === 'value') {
            return $runtimeValue;
        }

        if ($node instanceof ArrayNode) {
            return $this->resolveArrayNode($node, $runtimeValue);
        }

        throw new \InvalidArgumentException(sprintf(
            'Unsupported value node type "%s". Only constants, arrays, and $value placeholder are supported.',
            get_class($node)
        ));
    }

    private function resolveArrayNode(ArrayNode $node, mixed $runtimeValue): array
    {
        $values = [];
        $pairs = array_values($node->nodes);

        for ($i = 0; $i < count($pairs); $i += 2) {
            $values[] = $this->resolveValue($pairs[$i + 1], $runtimeValue);
        }

        return $values;
    }

    private function mapOperator(string $operator): string
    {
        return match ($operator) {
            '==' => '=',
            '===' => '=',
            '!=' => '!=',
            '!==' => '!=',
            '>', '>=', '<', '<=' => $operator,
            default => throw new \InvalidArgumentException(sprintf(
                'Unsupported comparison operator "%s" in query expression.',
                $operator
            )),
        };
    }

    private function validateOperator(string $operator): void
    {
        $allowed = ['==', '===', '!=', '!==', '>', '>=', '<', '<=', 'in', 'not in', 'contains', 'starts with', 'ends with'];

        if (! in_array($operator, $allowed, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Operator "%s" is not allowed in query expressions. Use arithmetic or functions outside of query context.',
                $operator
            ));
        }
    }
}
