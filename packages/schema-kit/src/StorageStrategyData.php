<?php

namespace Coda\SchemaKit;

final readonly class StorageStrategyData
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public StorageMode $mode,
        public array $config = [],
    ) {}

    public static function attribute(string $attribute): self
    {
        return new self(StorageMode::Attribute, ['attribute' => $attribute]);
    }

    public static function json(string $column, ?string $path = null): self
    {
        return new self(StorageMode::Json, array_filter([
            'column' => $column,
            'path' => $path,
        ], static fn (mixed $value): bool => $value !== null));
    }

    public static function normalized(array $config = []): self
    {
        return new self(StorageMode::Normalized, $config);
    }

    public static function relation(?string $relation = null, array $config = []): self
    {
        return new self(StorageMode::Relation, array_filter([
            'relation' => $relation,
            ...$config,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * @return array{mode: string, config: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode->value,
            'config' => $this->config,
        ];
    }
}
