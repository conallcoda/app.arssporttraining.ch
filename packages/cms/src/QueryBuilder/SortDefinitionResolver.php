<?php

namespace Coda\Cms\QueryBuilder;

use Coda\ExpressionKit\SortExpressionCompiler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedSort;

class SortDefinitionResolver
{
    private SortExpressionCompiler $compiler;

    public function __construct(
        private ?Model $model = null,
    ) {
        $this->compiler = new SortExpressionCompiler;
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
        return ['type' => 'raw', 'sql' => $this->compiler->compile($segment)];
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

        $selectExpr = $this->compiler->compile($columnExpr);

        $fkColumn = "{$sourceTable}.{$foreignKey}";

        return [
            'type' => 'raw',
            'sql' => "(SELECT {$selectExpr} FROM {$relatedTable} WHERE {$relatedTable}.id = {$fkColumn})",
        ];
    }

    private function splitSegments(string $spec): array
    {
        return $this->compiler->splitSegments($spec);
    }
}
