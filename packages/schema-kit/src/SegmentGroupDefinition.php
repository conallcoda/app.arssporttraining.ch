<?php

namespace Coda\SchemaKit;

use Illuminate\Support\Str;

final class SegmentGroupDefinition
{
    private ?string $label = null;

    private ?string $description = null;

    private ?string $scopeType = null;

    private int|string|null $scopeId = null;

    private array $meta = [];

    public function __construct(
        private readonly string $slug,
    ) {}

    public static function make(string $slug): static
    {
        return new static($slug);
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function scope(?string $scopeType, int|string|null $scopeId = null): static
    {
        $this->scopeType = $scopeType;
        $this->scopeId = $scopeType === null ? null : $scopeId;

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

    public function getLabel(): string
    {
        return $this->label ?? Str::headline($this->slug);
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getScopeType(): ?string
    {
        return $this->scopeType;
    }

    public function getScopeId(): int|string|null
    {
        return $this->scopeId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug(),
            'label' => $this->getLabel(),
            'description' => $this->getDescription(),
            'scope_type' => $this->getScopeType(),
            'scope_id' => $this->getScopeId(),
            'meta' => $this->allMeta(),
        ];
    }
}
