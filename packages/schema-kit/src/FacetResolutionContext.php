<?php

namespace Coda\SchemaKit;

final class FacetResolutionContext
{
    /** @var array<string, array<string, int|string>> */
    private array $taxonomyTerms = [];

    /** @var array<string, bool> */
    private array $segments = [];

    private ScopeReference $scope;

    public function __construct(
        private ?string $schemaKey = null,
    ) {
        $this->scope = ScopeReference::make();
    }

    public static function make(?string $schemaKey = null): static
    {
        return new static($schemaKey);
    }

    public function schema(?string $schemaKey): static
    {
        $this->schemaKey = $schemaKey;

        return $this;
    }

    public function scope(?string $type, int|string|null $id = null): static
    {
        $this->scope = ScopeReference::make($type, $id);

        return $this;
    }

    public function taxonomyTerm(string $taxonomyType, int|string|null $term): static
    {
        if ($term === null) {
            return $this;
        }

        $this->taxonomyTerms[$taxonomyType][(string) $term] = $term;

        return $this;
    }

    /**
     * @param  array<int, int|string|null>  $terms
     */
    public function taxonomyTerms(string $taxonomyType, array $terms): static
    {
        foreach ($terms as $term) {
            $this->taxonomyTerm($taxonomyType, $term);
        }

        return $this;
    }

    public function segment(string $segmentSlug): static
    {
        $this->segments[$segmentSlug] = true;

        return $this;
    }

    /**
     * @param  array<int, string>  $segmentSlugs
     */
    public function segments(array $segmentSlugs): static
    {
        foreach ($segmentSlugs as $segmentSlug) {
            $this->segment($segmentSlug);
        }

        return $this;
    }

    public function schemaKey(): ?string
    {
        return $this->schemaKey;
    }

    public function scopeReference(): ScopeReference
    {
        return $this->scope;
    }

    public function hasTaxonomyType(string $taxonomyType): bool
    {
        return isset($this->taxonomyTerms[$taxonomyType]) && $this->taxonomyTerms[$taxonomyType] !== [];
    }

    public function hasTaxonomyTerm(string $taxonomyType, int|string|null $term): bool
    {
        if ($term === null) {
            return $this->hasTaxonomyType($taxonomyType);
        }

        return array_key_exists((string) $term, $this->taxonomyTerms[$taxonomyType] ?? []);
    }

    public function hasSegment(string $segmentSlug): bool
    {
        return isset($this->segments[$segmentSlug]);
    }
}
