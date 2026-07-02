<?php

namespace Coda\SchemaKit;

final readonly class ScopeReference
{
    public function __construct(
        public ?string $type = null,
        public int|string|null $id = null,
    ) {}

    public static function make(?string $type = null, int|string|null $id = null): static
    {
        return new static($type, $type === null ? null : $id);
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'scope_type' => $this->type,
            'scope_id' => $this->id,
        ];
    }
}
