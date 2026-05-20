<?php

namespace Coda\SchemaKit;

use Closure;

final class ScopeDefinition
{
    private ?string $label = null;

    private bool $required = false;

    private ?string $attribute = null;

    private ?string $field = null;

    private string $contextPath = 'id';

    private ?Closure $queryUsing = null;

    private array $meta = [];

    public function __construct(
        private readonly string $key,
    ) {}

    public static function make(string $key): static
    {
        return new static($key);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    public function attribute(?string $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function field(?string $field): static
    {
        $this->field = $field;

        return $this;
    }

    public function contextPath(string $contextPath): static
    {
        $this->contextPath = $contextPath;

        return $this;
    }

    public function queryUsing(?Closure $queryUsing): static
    {
        $this->queryUsing = $queryUsing;

        return $this;
    }

    public function setMeta(string $key, mixed $value): static
    {
        $this->meta[$key] = $value;

        return $this;
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    public function allMeta(): array
    {
        return $this->meta;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getAttribute(): ?string
    {
        return $this->attribute;
    }

    public function getField(): ?string
    {
        return $this->field;
    }

    public function getContextPath(): string
    {
        return $this->contextPath;
    }

    public function getQueryUsing(): ?Closure
    {
        return $this->queryUsing;
    }
}
