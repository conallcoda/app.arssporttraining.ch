<?php

namespace Coda\SchemaKit\Runtime\Services;

use Coda\SchemaKit\ScopeReference;
use Coda\SchemaKit\Runtime\Models\StoredFieldFact;
use Coda\SchemaKit\Runtime\Models\StoredFieldRevision;
use Illuminate\Database\Eloquent\Model;

class StoredFieldFactProjector
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function replaceFacetFacts(
        Model $entity,
        string $schemaKey,
        string $facetKey,
        array $rows,
        ?ScopeReference $scope = null,
    ): void {
        $resolvedScope = $scope ?? $this->legacyScope($entity);

        StoredFieldFact::query()
            ->where('entity_type', $entity::class)
            ->where('entity_id', $entity->getKey())
            ->where('schema_key', $schemaKey)
            ->where('facet_key', $facetKey)
            ->delete();

        foreach ($rows as $row) {
            StoredFieldFact::query()->create([
                'entity_type' => $entity::class,
                'entity_id' => $entity->getKey(),
                ...$resolvedScope->toArray(),
                ...$row,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function baseRow(
        Model $entity,
        string $schemaKey,
        string $facetKey,
        string $fieldKey,
        StoredFieldRevision $revision,
        ?ScopeReference $scope = null,
    ): array {
        $resolvedScope = $scope ?? $this->legacyScope($entity);

        return [
            'schema_key' => $schemaKey,
            'facet_key' => $facetKey,
            'field_key' => $fieldKey,
            'field_revision_id' => $revision->id,
            'facet_revision_id' => $revision->facet_revision_id,
            ...$resolvedScope->toArray(),
        ];
    }

    protected function legacyScope(Model $entity): ScopeReference
    {
        $scopeId = $entity->getAttribute('conference_edition_id');

        if ($scopeId === null) {
            return ScopeReference::make();
        }

        return ScopeReference::make('conference_edition', $scopeId);
    }
}
