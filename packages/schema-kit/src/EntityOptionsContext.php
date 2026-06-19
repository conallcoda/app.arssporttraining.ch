<?php

namespace Coda\SchemaKit;

final class EntityOptionsContext
{
    /**
     * @param  array<string, int|string>  $scopeValues
     */
    public function __construct(
        private readonly EntityDefinition $entity,
        private readonly array $data,
        private readonly array $scopeValues,
    ) {}

    public function entity(): EntityDefinition
    {
        return $this->entity;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function scopeValue(?string $scopeKey = null): int|string|null
    {
        if ($scopeKey !== null) {
            $value = $this->scopeValues[$scopeKey] ?? null;

            return is_int($value) || is_string($value) ? $value : null;
        }

        $first = reset($this->scopeValues);

        return is_int($first) || is_string($first) ? $first : null;
    }

    /**
     * @param  string|array<int, string>  $paths
     */
    public function intValue(string|array $paths): ?int
    {
        foreach ((array) $paths as $path) {
            $value = data_get($this->data, $path);

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, int|string>
     */
    public function scopeValues(): array
    {
        return $this->scopeValues;
    }
}
