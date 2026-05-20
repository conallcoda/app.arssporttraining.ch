<?php

namespace Coda\SchemaKit;

final readonly class FacetDefinitionData
{
    /**
     * @param  array<int, FieldDefinitionData>  $fields
     * @param  array<int, FacetApplicabilityRuleData>  $applicability
     */
    public function __construct(
        public string $key,
        public ?string $label,
        public ?string $description,
        public ?string $dataClass,
        public ?string $dataPath,
        public bool $inferFields,
        public ?StorageStrategyData $storage,
        public array $fields = [],
        public array $applicability = [],
        public array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'data_class' => $this->dataClass,
            'data_path' => $this->dataPath,
            'infer_fields' => $this->inferFields,
            'storage' => $this->storage?->toArray(),
            'fields' => array_map(
                static fn (FieldDefinitionData $field): array => $field->toArray(),
                $this->fields,
            ),
            'applicability' => array_map(
                static fn (FacetApplicabilityRuleData $rule): array => $rule->toArray(),
                $this->applicability,
            ),
            'meta' => $this->meta,
        ];
    }
}
