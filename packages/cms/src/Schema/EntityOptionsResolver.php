<?php

namespace Coda\Cms\Schema;

use Coda\Cms\Registry;
use Coda\SchemaKit\EntityDefinition;
use Coda\SchemaKit\EntityOptions;
use Coda\SchemaKit\EntityOptionsContext;
use Coda\SchemaKit\IdentityDefinition;
use Coda\SchemaKit\ScopeDataResolver;
use Coda\SchemaKit\ScopeDefinition;
use Coda\SchemaKit\SchemaRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use ReflectionFunction;

final class EntityOptionsResolver
{
    public function __construct(
        private readonly SchemaRegistry $registry,
        private readonly ScopeDataResolver $scopeDataResolver,
        private readonly Registry $cmsRegistry,
    ) {}

    /**
     * @return array<int|string, string>
     */
    public function resolveList(EntityOptions $options, array $data = []): array
    {
        [$entity, $records, $context] = $this->resolveRecords($options, $data);
        $resolved = [];

        foreach ($records as $record) {
            $resolved[$this->resolveValue($options, $record, $context)] = $this->resolveLabel($entity, $options, $record, $context);
        }

        return $resolved;
    }

    /**
     * @return list<array{value: int|string, name: string, children: list<mixed>}>
     */
    public function resolveTree(EntityOptions $options, array $data = []): array
    {
        [$entity, $records, $context] = $this->resolveRecords($options, $data);
        $recordsByParent = [];
        $recordIds = [];

        foreach ($records as $record) {
            $recordIds[(string) $this->resolveValue($options, $record, $context)] = true;
        }

        foreach ($records as $record) {
            $parentId = $record->getAttribute($options->getParentAttribute());
            $parentKey = $parentId === null || ! array_key_exists((string) $parentId, $recordIds)
                ? '__root__'
                : (string) $parentId;
            $recordsByParent[$parentKey][] = $record;
        }

        return $this->buildTreeBranch($entity, $options, $context, $recordsByParent, '__root__');
    }

    /**
     * @return array{0: EntityDefinition, 1: Collection<int, Model>, 2: EntityOptionsContext}
     */
    private function resolveRecords(EntityOptions $options, array $data): array
    {
        $entity = $this->registry->entity($options->entityName());
        $scopeEntity = $this->registry->entity($options->scopeEntityName());
        $modelClass = $entity->getModelClass();

        if (! is_string($modelClass) || $modelClass === '') {
            throw new \InvalidArgumentException("Entity [{$entity->name()}] is missing a model class for entity-backed options.");
        }

        /** @var Builder $query */
        $query = $modelClass::query();

        $with = array_values(array_unique([
            ...$entity->getWith(),
            ...$options->getWith(),
        ]));

        if ($with !== []) {
            $query->with($with);
        }

        $scopeValues = $this->applyEntityScopes($query, $scopeEntity, $data);
        $context = new EntityOptionsContext($entity, $data, $scopeValues);
        $this->applyCustomQuery($query, $options, $context, $entity);

        foreach ($options->getOrderBy() as $ordering) {
            $query->orderBy($ordering['column'], $ordering['direction']);
        }

        /** @var Collection<int, Model> $records */
        $records = $query->get();

        return [$entity, $records, $context];
    }

    /**
     * @param  array<string, list<Model>>  $recordsByParent
     * @return list<array{value: int|string, name: string, children: list<mixed>}>
     */
    private function buildTreeBranch(
        EntityDefinition $entity,
        EntityOptions $options,
        EntityOptionsContext $context,
        array $recordsByParent,
        string $parentKey,
    ): array {
        $records = $recordsByParent[$parentKey] ?? [];
        $branch = [];

        foreach ($records as $record) {
            $value = $this->resolveValue($options, $record, $context);

            $branch[] = [
                'value' => $value,
                'name' => $this->resolveLabel($entity, $options, $record, $context),
                'children' => $this->buildTreeBranch($entity, $options, $context, $recordsByParent, (string) $value),
            ];
        }

        return $branch;
    }

    /**
     * @return array<string, int|string>
     */
    private function applyEntityScopes(Builder $query, EntityDefinition $entity, array $data): array
    {
        $resolved = [];

        foreach ($entity->getScopes() as $scopeKey => $scope) {
            $fallback = $this->cmsRegistry->currentContextValue($scope->getContextPath());
            $value = $this->scopeDataResolver->resolve($entity, $scopeKey, $data, $fallback);

            if ($value === null || $value === '') {
                continue;
            }

            $resolved[$scopeKey] = $value;
            $this->applyScope($query, $scope, $value, $data, $entity);
        }

        return $resolved;
    }

    private function applyScope(Builder $query, ScopeDefinition $scope, int|string $value, array $data, EntityDefinition $entity): void
    {
        $queryUsing = $scope->getQueryUsing();

        if ($queryUsing !== null) {
            $reflection = new ReflectionFunction($queryUsing);
            $parameterCount = $reflection->getNumberOfParameters();

            match (true) {
                $parameterCount <= 1 => $queryUsing($query),
                $parameterCount === 2 => $queryUsing($query, $value),
                $parameterCount === 3 => $queryUsing($query, $value, $data),
                $parameterCount === 4 => $queryUsing($query, $value, $data, $scope),
                default => $queryUsing($query, $value, $data, $scope, $entity),
            };

            return;
        }

        $attribute = $scope->getAttribute();

        if (is_string($attribute) && $attribute !== '') {
            $query->where($attribute, $value);
        }
    }

    private function applyCustomQuery(Builder $query, EntityOptions $options, EntityOptionsContext $context, EntityDefinition $entity): void
    {
        $queryUsing = $options->getQueryUsing();

        if ($queryUsing === null) {
            return;
        }

        $reflection = new ReflectionFunction($queryUsing);
        $parameterCount = $reflection->getNumberOfParameters();

        match (true) {
            $parameterCount <= 1 => $queryUsing($query),
            $parameterCount === 2 => $queryUsing($query, $context),
            default => $queryUsing($query, $context, $entity),
        };
    }

    private function resolveValue(EntityOptions $options, Model $record, EntityOptionsContext $context): int|string
    {
        $resolver = $options->getValueUsing();

        if ($resolver !== null) {
            $reflection = new ReflectionFunction($resolver);
            $parameterCount = $reflection->getNumberOfParameters();

            $resolved = match (true) {
                $parameterCount <= 1 => $resolver($record),
                $parameterCount === 2 => $resolver($record, $context),
                default => $resolver($record, $context, $context->entity()),
            };

            if (is_int($resolved) || (is_string($resolved) && $resolved !== '')) {
                return $resolved;
            }
        }

        return $record->getKey();
    }

    private function resolveLabel(EntityDefinition $entity, EntityOptions $options, Model $record, EntityOptionsContext $context): string
    {
        $resolver = $options->getLabelUsing();

        if ($resolver !== null) {
            $reflection = new ReflectionFunction($resolver);
            $parameterCount = $reflection->getNumberOfParameters();

            $resolved = match (true) {
                $parameterCount <= 1 => $resolver($record),
                $parameterCount === 2 => $resolver($record, $context),
                default => $resolver($record, $context, $entity),
            };

            if (is_string($resolved) && $resolved !== '') {
                return $resolved;
            }
        }

        $identity = $entity->getIdentity();
        $identityLabel = $identity instanceof IdentityDefinition ? $this->resolveIdentityTitle($identity, $record) : null;

        foreach ([$identityLabel, $record->getAttribute('name'), $record->getAttribute('display_name'), $record->getAttribute('title')] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return ($entity->getLabel() ?? 'Record').' #'.$record->getKey();
    }

    private function resolveIdentityTitle(IdentityDefinition $identity, Model $record): ?string
    {
        $title = $identity->getTitle();

        if (is_string($title) && $title !== '') {
            $resolved = data_get($record, $title);

            return is_string($resolved) && $resolved !== '' ? $resolved : null;
        }

        if ($title instanceof \Closure) {
            $reflection = new ReflectionFunction($title);
            $parameterCount = $reflection->getNumberOfParameters();
            $resolved = $parameterCount > 0 ? $title($record) : $title();

            return is_string($resolved) && $resolved !== '' ? $resolved : null;
        }

        return null;
    }
}
