<?php

namespace Coda\SchemaKit;

use Illuminate\Support\Str;

final class ScopeDataResolver
{
    public function resolve(EntityDefinition $entity, string $scopeKey, array $data, mixed $fallback = null): int|string|null
    {
        $scope = $entity->requireScope($scopeKey);

        foreach ($this->candidatePaths($scope, $scopeKey) as $path) {
            $resolved = $this->extractId(data_get($data, $path));

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return $this->extractId($fallback);
    }

    /**
     * @return list<string>
     */
    private function candidatePaths(ScopeDefinition $scope, string $scopeKey): array
    {
        $paths = [];

        foreach ($scope->getDataPaths() as $path) {
            $paths[] = $path;
        }

        if (is_string($scope->getField()) && $scope->getField() !== '') {
            $paths[] = $scope->getField();
            $paths[] = Str::camel($scope->getField());
        }

        if (is_string($scope->getAttribute()) && $scope->getAttribute() !== '') {
            $paths[] = $scope->getAttribute();
            $paths[] = Str::camel($scope->getAttribute());
        }

        foreach ($this->relationAliases($scopeKey, $scope) as $alias) {
            $paths[] = "{$alias}.id";
        }

        return array_values(array_unique(array_filter($paths, static fn (mixed $path): bool => is_string($path) && $path !== '')));
    }

    /**
     * @return list<string>
     */
    private function relationAliases(string $scopeKey, ScopeDefinition $scope): array
    {
        $aliases = [];

        if (is_string($scope->getMeta('relation')) && $scope->getMeta('relation') !== '') {
            $aliases[] = (string) $scope->getMeta('relation');
        }

        $aliases[] = $scopeKey;
        $aliases[] = Str::camel($scopeKey);

        if (str_contains($scopeKey, '_')) {
            $aliases[] = Str::afterLast($scopeKey, '_');
        }

        foreach ([ $scope->getField(), $scope->getAttribute() ] as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            if (str_ends_with($candidate, '_id')) {
                $aliases[] = Str::beforeLast($candidate, '_id');
                $aliases[] = Str::camel(Str::beforeLast($candidate, '_id'));
            } else {
                $aliases[] = $candidate;
                $aliases[] = Str::camel($candidate);
            }
        }

        return array_values(array_unique(array_filter($aliases, static fn (mixed $alias): bool => is_string($alias) && $alias !== '')));
    }

    private function extractId(mixed $value): int|string|null
    {
        if (is_int($value) || is_string($value)) {
            return $value === '' ? null : $value;
        }

        if (is_array($value)) {
            return $this->extractId($value['id'] ?? null);
        }

        if (is_object($value) && isset($value->id)) {
            return $this->extractId($value->id);
        }

        return null;
    }
}
