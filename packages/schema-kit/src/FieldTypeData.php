<?php

namespace Coda\SchemaKit;

final readonly class FieldTypeData
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public string $kind,
        public array $config = [],
    ) {}

    /**
     * @return array{kind: string, config: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'config' => $this->config,
        ];
    }
}
