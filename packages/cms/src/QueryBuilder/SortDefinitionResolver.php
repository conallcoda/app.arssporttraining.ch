<?php

namespace Coda\Cms\QueryBuilder;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedSort;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;
use Symfony\Component\ExpressionLanguage\Node\GetAttrNode;
use Symfony\Component\ExpressionLanguage\Node\NameNode;
use Symfony\Component\ExpressionLanguage\Node\Node;

class SortDefinitionResolver
{
    private ExpressionLanguage $language;

    public function __construct(
        private ?Model $model = null,
    ) {
        $this->language = new ExpressionLanguage;
    }

    /** @return array<int, string|AllowedSort> */
    public function resolve(array $definitions): array
    {
        return array_map(fn (mixed $definition) => $this->resolveDefinition($definition), $definitions);
    }

    private function resolveDefinition(mixed $definition): string|AllowedSort
    {
        if ($definition instanceof AllowedSort) {
            return $definition;
        }

        if (! is_string($definition)) {
            throw new \InvalidArgumentException(sprintf(
                'Sort definition must be a string or AllowedSort instance, got %s.',
                get_debug_type($definition)
            ));
        }

        if (! str_contains($definition, '=>')) {
            $snakeCase = Str::snake($definition);

            if ($snakeCase !== $definition) {
                return AllowedSort::field($definition, $snakeCase);
            }

            return $definition;
        }

        return $this->parseArrowNotation($definition);
    }

    private function parseArrowNotation(string $definition): AllowedSort
    {
        [$name, $spec] = array_map('trim', explode('=>', $definition, 2));

        if (str_starts_with($spec, 'relation(')) {
            return $this->parseLegacyRelationSort($name, $spec);
        }

        return $this->parseExpressionSort($name, $spec);
    }

    private function parseExpressionSort(string $name, string $spec): AllowedSort
    {
        $segments = $this->splitSegments($spec);
        $orderBys = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);

            $direction = 'asc';
            if (preg_match('/\s+(asc|desc)$/i', $segment, $dirMatch)) {
                $direction = strtolower($dirMatch[1]);
                $segment = trim(substr($segment, 0, -strlen($dirMatch[0])));
            }

            $parsed = $this->parseSegment($segment);
            $parsed['direction'] = $direction;
            $orderBys[] = $parsed;
        }

        if (count($orderBys) === 1 && $orderBys[0]['type'] === 'field' && ! str_contains($spec, '.')) {
            return AllowedSort::field($name, $orderBys[0]['sql']);
        }

        return AllowedSort::callback($name, function ($query, bool $descending) use ($orderBys): void {
            foreach ($orderBys as $orderBy) {
                $dir = $descending
                    ? ($orderBy['direction'] === 'asc' ? 'desc' : 'asc')
                    : $orderBy['direction'];

                if ($orderBy['type'] === 'field') {
                    $query->orderBy($orderBy['sql'], $dir);
                } else {
                    $query->orderBy(DB::raw($orderBy['sql']), $dir);
                }
            }
        });
    }

    /** @return array{type: string, sql: string} */
    private function parseSegment(string $segment): array
    {
        if (! str_contains($segment, '.')) {
            return ['type' => 'field', 'sql' => $segment];
        }

        $firstIdentifier = explode('.', $segment, 2)[0];

        if ($this->isRelationship($firstIdentifier)) {
            return $this->parseRelationshipExpression($segment);
        }

        return $this->parseLocalExpression($segment);
    }

    private function isRelationship(string $name): bool
    {
        if (! $this->model) {
            return false;
        }

        if (! method_exists($this->model, $name)) {
            return false;
        }

        try {
            return $this->model->$name() instanceof BelongsTo;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{type: string, sql: string} */
    private function parseLocalExpression(string $segment): array
    {
        $names = $this->extractIdentifiers($segment);
        $parsed = $this->language->parse($segment, $names);
        $sqlExpr = $this->walkSortExpression($parsed->getNodes());

        return ['type' => 'raw', 'sql' => $sqlExpr];
    }

    /** @return array{type: string, sql: string} */
    private function parseRelationshipExpression(string $segment): array
    {
        $firstDot = strpos($segment, '.');
        $relationName = substr($segment, 0, $firstDot);
        $columnExpr = substr($segment, $firstDot + 1);

        $relation = $this->model->$relationName();
        $relatedTable = $relation->getRelated()->getTable();
        $foreignKey = $relation->getForeignKeyName();
        $sourceTable = $this->model->getTable();

        $names = $this->extractIdentifiers($columnExpr);
        $parsed = $this->language->parse($columnExpr, $names);
        $selectExpr = $this->walkSortExpression($parsed->getNodes());

        $fkColumn = "{$sourceTable}.{$foreignKey}";

        return [
            'type' => 'raw',
            'sql' => "(SELECT {$selectExpr} FROM {$relatedTable} WHERE {$relatedTable}.id = {$fkColumn})",
        ];
    }

    private function walkSortExpression(Node $node): string
    {
        if ($node instanceof NameNode) {
            return $node->attributes['name'];
        }

        if ($node instanceof ConstantNode) {
            return $node->attributes['value'];
        }

        if ($node instanceof GetAttrNode) {
            $type = $node->attributes['type'];

            if ($type === GetAttrNode::PROPERTY_CALL) {
                $object = $this->walkSortExpression($node->nodes['node']);
                $property = $node->nodes['attribute']->attributes['value'];

                return $property;
            }

            if ($type === GetAttrNode::METHOD_CALL) {
                return $this->walkMethodCall($node);
            }
        }

        throw new \InvalidArgumentException(sprintf(
            'Unsupported node type "%s" in sort expression.',
            get_class($node)
        ));
    }

    private function walkMethodCall(GetAttrNode $node): string
    {
        $methodName = $node->nodes['attribute']->attributes['value'];
        $baseNode = $node->nodes['node'];

        if ($methodName === 'concat') {
            $firstColumn = $this->walkSortExpression($baseNode);
            $args = $this->extractMethodArgs($node->nodes['arguments']);

            $allColumns = array_merge([$firstColumn], $args);

            return 'CONCAT('.implode(", ' ', ", $allColumns).')';
        }

        throw new \InvalidArgumentException(sprintf(
            'Unsupported method "%s" in sort expression. Supported: concat.',
            $methodName
        ));
    }

    private function extractMethodArgs(Node $argumentsNode): array
    {
        $args = [];
        $pairs = array_values($argumentsNode->nodes);

        for ($i = 0; $i < count($pairs); $i += 2) {
            $args[] = $this->walkSortExpression($pairs[$i + 1]);
        }

        return $args;
    }

    private function extractIdentifiers(string $expression): array
    {
        preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\b/', $expression, $matches);

        $reserved = ['concat', 'asc', 'desc', 'true', 'false', 'null'];

        return array_values(array_unique(array_filter(
            $matches[1],
            fn (string $name) => ! in_array($name, $reserved, true)
        )));
    }

    private function splitSegments(string $spec): array
    {
        $segments = [];
        $current = '';
        $depth = 0;

        for ($i = 0; $i < strlen($spec); $i++) {
            $char = $spec[$i];

            if ($char === '(') {
                $depth++;
                $current .= $char;

                continue;
            }

            if ($char === ')') {
                $depth--;
                $current .= $char;

                continue;
            }

            if ($char === ',' && $depth === 0) {
                $segments[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $segments[] = $current;
        }

        return $segments;
    }

    private function parseLegacyRelationSort(string $name, string $spec): AllowedSort
    {
        $inner = substr($spec, strlen('relation('), -1);
        $args = $this->parseLegacyRelationArgs($inner);

        if (count($args) < 3) {
            throw new \InvalidArgumentException(sprintf(
                'Relation sort requires at least 3 arguments (table, selectExpr, foreignKey), got %d in: %s',
                count($args),
                $spec
            ));
        }

        $table = trim($args[0]);
        $selectExpr = trim($args[1], ' "\'');
        $foreignKey = trim($args[2]);
        $sourceTable = isset($args[3]) ? trim($args[3]) : null;

        return AllowedSort::callback($name, function ($query, bool $descending) use ($table, $selectExpr, $foreignKey, $sourceTable): void {
            $fkColumn = $sourceTable ? "{$sourceTable}.{$foreignKey}" : $foreignKey;
            $direction = $descending ? 'desc' : 'asc';

            $query->orderBy(
                DB::raw("(SELECT {$selectExpr} FROM {$table} WHERE {$table}.id = {$fkColumn})"),
                $direction,
            );
        });
    }

    private function parseLegacyRelationArgs(string $inner): array
    {
        $args = [];
        $current = '';
        $depth = 0;
        $inQuote = false;
        $quoteChar = null;

        for ($i = 0; $i < strlen($inner); $i++) {
            $char = $inner[$i];

            if ($inQuote) {
                if ($char === $quoteChar && ($i === 0 || $inner[$i - 1] !== '\\')) {
                    $inQuote = false;
                }
                $current .= $char;

                continue;
            }

            if ($char === '"' || $char === "'") {
                $inQuote = true;
                $quoteChar = $char;
                $current .= $char;

                continue;
            }

            if ($char === '(') {
                $depth++;
                $current .= $char;

                continue;
            }

            if ($char === ')') {
                $depth--;
                $current .= $char;

                continue;
            }

            if ($char === ',' && $depth === 0) {
                $args[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $args[] = $current;
        }

        return $args;
    }
}
