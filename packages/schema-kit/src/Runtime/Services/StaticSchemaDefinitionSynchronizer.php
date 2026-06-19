<?php

namespace Coda\SchemaKit\Runtime\Services;

use Coda\SchemaKit\EntityDefinition;
use Coda\SchemaKit\FacetDefinitionData;
use Coda\SchemaKit\FieldDefinitionData;
use Coda\SchemaKit\SegmentDefinition;
use Coda\SchemaKit\SegmentGroupDefinition;
use Coda\SchemaKit\SegmentationDefinition;
use Coda\SchemaKit\Runtime\Models\StoredFacet;
use Coda\SchemaKit\Runtime\Models\StoredFacetApplicabilityRule;
use Coda\SchemaKit\Runtime\Models\StoredFacetRevision;
use Coda\SchemaKit\Runtime\Models\StoredField;
use Coda\SchemaKit\Runtime\Models\StoredFieldRevision;
use Coda\SchemaKit\Runtime\Models\StoredSegment;
use Coda\SchemaKit\Runtime\Models\StoredSegmentGroup;
use Coda\SchemaKit\Runtime\Models\StoredSchema;
use Coda\SchemaKit\SchemaRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StaticSchemaDefinitionSynchronizer
{
    public function __construct(
        private readonly SchemaRegistry $registry,
    ) {}

    public function syncEntity(string $entityKey): StoredSchema
    {
        $entity = $this->registry->entity($entityKey);

        return DB::transaction(fn (): StoredSchema => $this->persistEntityDefinition($entity));
    }

    protected function persistEntityDefinition(EntityDefinition $entity): StoredSchema
    {
        $definition = $entity->toDefinitionData();

        $schema = StoredSchema::query()->updateOrCreate(
            ['key' => $definition->key],
            [
                'label' => $definition->label,
                'plural_label' => $definition->pluralLabel,
                'model_class' => $definition->modelClass,
                'meta' => $definition->meta,
                'is_active' => true,
            ],
        );

        foreach ($definition->facets as $facetDefinition) {
            $this->persistFacetDefinition($schema, $facetDefinition);
        }

        $this->persistSegmentation($schema, $entity->getSegmentation());

        return $schema->fresh(['facets.currentRevision', 'segmentGroups', 'segments']);
    }

    protected function persistFacetDefinition(StoredSchema $schema, FacetDefinitionData $definition): StoredFacet
    {
        $facet = StoredFacet::query()->updateOrCreate(
            [
                'schema_definition_id' => $schema->id,
                'key' => $definition->key,
            ],
            [
                'facet_group_key' => $definition->facetGroup,
                'label' => $definition->label,
                'description' => $definition->description,
                'data_class' => $definition->dataClass,
                'data_path' => $definition->dataPath,
                'infer_fields' => $definition->inferFields,
                'storage_mode' => $definition->storage?->mode->value,
                'storage_config' => $definition->storage?->config,
                'meta' => $definition->meta,
                'is_dynamic' => false,
                'is_active' => true,
            ],
        );

        $revisionPayload = $definition->toArray();
        $revisionHash = $this->hashPayload($revisionPayload);
        $currentVersion = (int) ($facet->currentRevision?->version ?? $facet->revisions()->max('version') ?? 0);

        $revision = StoredFacetRevision::query()->firstOrCreate(
            [
                'facet_id' => $facet->id,
                'content_hash' => $revisionHash,
            ],
            [
                'version' => $currentVersion + 1,
                'definition_json' => $revisionPayload,
                'published_at' => Carbon::now(),
                'is_current' => true,
            ],
        );

        if ($facet->current_revision_id !== $revision->id) {
            StoredFacetRevision::query()
                ->where('facet_id', $facet->id)
                ->update(['is_current' => false]);

            $revision->forceFill(['is_current' => true])->save();
            $facet->forceFill(['current_revision_id' => $revision->id])->save();
        }

        StoredFacetApplicabilityRule::query()
            ->where('facet_revision_id', $revision->id)
            ->delete();

        foreach ($definition->applicability as $rule) {
            StoredFacetApplicabilityRule::query()->create([
                'facet_revision_id' => $revision->id,
                'schema_key' => $rule->schemaKey,
                'scope_type' => $rule->scopeType,
                'scope_id' => $rule->scopeId,
                'taxonomy_type' => $rule->taxonomyType,
                'taxonomy_term_id' => $rule->taxonomyTerm,
                'segment_slug' => $rule->segmentSlug,
                'priority' => $rule->priority,
                'mode' => $rule->mode,
            ]);
        }

        foreach ($definition->fields as $fieldDefinition) {
            $this->persistFieldDefinition($facet, $revision, $fieldDefinition);
        }

        return $facet->fresh(['currentRevision']);
    }

    protected function persistFieldDefinition(StoredFacet $facet, StoredFacetRevision $facetRevision, FieldDefinitionData $definition): StoredField
    {
        $field = StoredField::query()->updateOrCreate(
            [
                'facet_id' => $facet->id,
                'key' => $definition->key,
            ],
            [
                'label' => $definition->label,
                'definition_type' => $definition->definitionType,
                'query_strategy' => $definition->queryStrategy->value,
                'is_repeatable' => $definition->repeatable,
                'is_active' => true,
            ],
        );

        $revisionPayload = $definition->toArray();
        $revisionHash = $this->hashPayload($revisionPayload);
        $currentVersion = (int) ($field->currentRevision?->version ?? $field->revisions()->max('version') ?? 0);

        $revision = StoredFieldRevision::query()->firstOrCreate(
            [
                'field_id' => $field->id,
                'content_hash' => $revisionHash,
            ],
            [
                'facet_revision_id' => $facetRevision->id,
                'version' => $currentVersion + 1,
                'field_type' => $definition->fieldType?->kind,
                'type_config' => $definition->fieldType?->config,
                'storage_mode' => $definition->storage?->mode->value ?? $facet->storage_mode,
                'storage_config' => $definition->storage?->config ?? $facet->storage_config,
                'attribute_name' => $definition->attribute,
                'required' => $definition->required,
                'readable' => $definition->readable,
                'writable' => $definition->writable,
                'form_visible' => $definition->formVisible,
                'help' => $definition->help,
                'meta' => $definition->meta,
                'published_at' => Carbon::now(),
                'is_current' => true,
            ],
        );

        if ($field->current_revision_id !== $revision->id) {
            StoredFieldRevision::query()
                ->where('field_id', $field->id)
                ->update(['is_current' => false]);

            $revision->forceFill([
                'facet_revision_id' => $facetRevision->id,
                'is_current' => true,
            ])->save();

            $field->forceFill(['current_revision_id' => $revision->id])->save();
        }

        return $field->fresh(['currentRevision']);
    }

    protected function hashPayload(array $payload): string
    {
        return sha1((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    protected function persistSegmentation(StoredSchema $schema, ?SegmentationDefinition $definition): void
    {
        if ($definition === null) {
            return;
        }

        $groupIds = [];
        $activeGroupSlugs = [];

        foreach ($definition->getGroups() as $group) {
            $storedGroup = $this->persistSegmentGroup($schema, $group);
            $groupIds[$group->slug()] = $storedGroup->id;
            $activeGroupSlugs[] = $group->slug();
        }

        $inactiveGroups = StoredSegmentGroup::query()
            ->where('schema_definition_id', $schema->id)
            ->where('is_system', true);

        if ($activeGroupSlugs !== []) {
            $inactiveGroups->whereNotIn('slug', $activeGroupSlugs);
        }

        $inactiveGroups->update(['is_active' => false]);

        $activeSegmentSlugs = [];

        foreach ($definition->getSegments() as $segment) {
            $groupSlug = $segment->getGroup() ?? $definition->defaultGroupSlug();
            $groupId = $groupIds[$groupSlug] ?? $this->persistSegmentGroup($schema, SegmentGroupDefinition::make($groupSlug))->id;
            $groupIds[$groupSlug] = $groupId;
            $activeGroupSlugs[] = $groupSlug;

            $this->persistSegment($schema, $segment, $groupId);
            $activeSegmentSlugs[] = $segment->slug();
        }

        $inactiveSegments = StoredSegment::query()
            ->where('schema_definition_id', $schema->id)
            ->where('definition_source', StoredSegment::SOURCE_SYSTEM);

        if ($activeSegmentSlugs !== []) {
            $inactiveSegments->whereNotIn('slug', $activeSegmentSlugs);
        }

        $inactiveSegments->update(['is_active' => false]);
    }

    protected function persistSegmentGroup(StoredSchema $schema, SegmentGroupDefinition $definition): StoredSegmentGroup
    {
        return StoredSegmentGroup::query()->updateOrCreate(
            [
                'schema_definition_id' => $schema->id,
                'slug' => $definition->slug(),
            ],
            [
                'label' => $definition->getLabel(),
                'description' => $definition->getDescription(),
                'scope_type' => $definition->getScopeType(),
                'scope_id' => $definition->getScopeId(),
                'meta' => $definition->allMeta(),
                'is_system' => true,
                'is_active' => true,
            ],
        );
    }

    protected function persistSegment(StoredSchema $schema, SegmentDefinition $definition, ?int $groupId): StoredSegment
    {
        return StoredSegment::query()->updateOrCreate(
            [
                'schema_definition_id' => $schema->id,
                'slug' => $definition->slug(),
            ],
            [
                'segment_group_id' => $groupId,
                'label' => $definition->getLabel(),
                'description' => $definition->getDescription(),
                'predicate' => $definition->getPredicate(),
                'scope_type' => $definition->getScopeType(),
                'scope_id' => $definition->getScopeId(),
                'definition_source' => StoredSegment::SOURCE_SYSTEM,
                'is_editable' => false,
                'is_deletable' => false,
                'meta' => $definition->allMeta(),
                'is_active' => true,
            ],
        );
    }
}
