<?php

namespace Coda\SchemaKit;

final readonly class FieldDefinitionData
{
    public function __construct(
        public string $key,
        public string $definitionType,
        public ?string $label,
        public ?string $help,
        public ?string $attribute,
        public bool $required,
        public bool $readable,
        public bool $writable,
        public bool $formVisible,
        public bool $repeatable,
        public ?FieldTypeData $fieldType,
        public ?StorageStrategyData $storage,
        public QueryStrategy $queryStrategy,
        public array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'definition_type' => $this->definitionType,
            'label' => $this->label,
            'help' => $this->help,
            'attribute' => $this->attribute,
            'required' => $this->required,
            'readable' => $this->readable,
            'writable' => $this->writable,
            'form_visible' => $this->formVisible,
            'repeatable' => $this->repeatable,
            'field_type' => $this->fieldType?->toArray(),
            'storage' => $this->storage?->toArray(),
            'query_strategy' => $this->queryStrategy->value,
            'meta' => $this->meta,
        ];
    }
}
