<?php

namespace Coda\SchemaKit;

final readonly class SchemaDefinitionData
{
    /**
     * @param  array<int, FacetDefinitionData>  $facets
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $key,
        public ?string $label,
        public ?string $pluralLabel,
        public ?string $modelClass,
        public array $facets = [],
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
            'plural_label' => $this->pluralLabel,
            'model_class' => $this->modelClass,
            'facets' => array_map(
                static fn (FacetDefinitionData $facet): array => $facet->toArray(),
                $this->facets,
            ),
            'meta' => $this->meta,
        ];
    }
}
