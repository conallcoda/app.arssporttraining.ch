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
        public int $priority = 0,
        public string $mode = 'include',
    ) {}

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
            'priority' => $this->priority,
            'mode' => $this->mode,
        ];
    }
}
