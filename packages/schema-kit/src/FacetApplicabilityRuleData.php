<?php

namespace Coda\SchemaKit;

final readonly class FacetApplicabilityRuleData
{
    public function __construct(
        public ?string $schemaKey = null,
        public ?string $scopeType = null,
        public int|string|null $scopeId = null,
        public ?string $taxonomyType = null,
        public int|string|null $taxonomyTerm = null,
        public ?string $segmentSlug = null,
        public int $priority = 0,
        public string $mode = 'include',
    ) {}

    public static function include(
        ?string $schemaKey = null,
        ?string $scopeType = null,
        int|string|null $scopeId = null,
        ?string $taxonomyType = null,
        int|string|null $taxonomyTerm = null,
        ?string $segmentSlug = null,
        int $priority = 0,
    ): static {
        return new static(
            schemaKey: $schemaKey,
            scopeType: $scopeType,
            scopeId: $scopeId,
            taxonomyType: $taxonomyType,
            taxonomyTerm: $taxonomyTerm,
            segmentSlug: $segmentSlug,
            priority: $priority,
            mode: 'include',
        );
    }

    public static function exclude(
        ?string $schemaKey = null,
        ?string $scopeType = null,
        int|string|null $scopeId = null,
        ?string $taxonomyType = null,
        int|string|null $taxonomyTerm = null,
        ?string $segmentSlug = null,
        int $priority = 0,
    ): static {
        return new static(
            schemaKey: $schemaKey,
            scopeType: $scopeType,
            scopeId: $scopeId,
            taxonomyType: $taxonomyType,
            taxonomyTerm: $taxonomyTerm,
            segmentSlug: $segmentSlug,
            priority: $priority,
            mode: 'exclude',
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'schema_key' => $this->schemaKey,
            'scope_type' => $this->scopeType,
            'scope_id' => $this->scopeId,
            'taxonomy_type' => $this->taxonomyType,
            'taxonomy_term' => $this->taxonomyTerm,
            'segment_slug' => $this->segmentSlug,
            'priority' => $this->priority,
            'mode' => $this->mode,
        ];
    }
}
