<?php

namespace Coda\SchemaKit;

use Illuminate\Support\Str;

final class TaxonomyDefinition
{
    private ?string $label = null;

    private ?string $termLabel = null;

    private bool $hierarchical = false;

    private bool $scopable = false;

    /** @var array<int, string> */
    private array $scopeKeys = [];

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

    public function termLabel(string $termLabel): static
    {
        $this->termLabel = $termLabel;

        return $this;
    }

    public function hierarchical(bool $hierarchical = true): static
    {
        $this->hierarchical = $hierarchical;

        return $this;
    }

    public function scopable(bool $scopable = true): static
    {
        $this->scopable = $scopable;

        return $this;
    }

    /**
     * @param  array<int, string>  $scopeKeys
     */
    public function scopeKeys(array $scopeKeys): static
    {
        $this->scopeKeys = array_values(array_filter(
            $scopeKeys,
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ));

        if ($this->scopeKeys !== []) {
            $this->scopable();
        }

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
        return $this->label ?? Str::headline($this->key);
    }

    public function getTermLabel(): string
    {
        return $this->termLabel ?? Str::singular($this->getLabel());
    }

    public function isHierarchical(): bool
    {
        return $this->hierarchical;
    }

    public function isScopable(): bool
    {
        return $this->scopable || $this->scopeKeys !== [];
    }

    /**
     * @return array<int, string>
     */
    public function getScopeKeys(): array
    {
        return $this->scopeKeys;
    }
}
