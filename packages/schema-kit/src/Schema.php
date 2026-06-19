<?php

namespace Coda\SchemaKit;

use Illuminate\Support\Str;

class Schema extends Entity
{
    private bool $schemaBooted = false;

    public static function make(?string $name = null): static
    {
        $name ??= static::defaultSchemaName();

        $schema = new static($name);
        $schema->bootSchema();

        return $schema;
    }

    public static function define(): static
    {
        return static::make();
    }

    protected function setUp(): void
    {
    }

    protected function modelClass(): ?string
    {
        return null;
    }

    protected static function defaultSchemaName(): string
    {
        if (static::class === self::class) {
            throw new \InvalidArgumentException('Base schema instances require an explicit name.');
        }

        $baseName = preg_replace('/Schema$/', '', class_basename(static::class)) ?: class_basename(static::class);

        return Str::snake($baseName);
    }

    private function bootSchema(): void
    {
        if ($this->schemaBooted) {
            return;
        }

        $this->schemaBooted = true;

        $modelClass = $this->modelClass();

        if (is_string($modelClass) && $modelClass !== '') {
            $this->model($modelClass);
        }

        $this->setUp();
    }
}
