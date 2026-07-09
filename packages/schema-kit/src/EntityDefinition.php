<?php

namespace Coda\SchemaKit;

use InvalidArgumentException;
use Closure;
use Illuminate\Support\Str;

class EntityDefinition
{
    private ?string $modelClass = null;

    private ?string $label = null;

    private ?string $pluralLabel = null;

    private array $meta = [];

    /** @var array<string, FacetDefinition> */
    private array $facets = [];

    /** @var array<string, FacetImportDefinition> */
    private array $facetImports = [];

    /** @var array<string, ViewDefinition> */
    private array $views = [];

    /** @var array<string, ScopeDefinition> */
    private array $scopes = [];

    /** @var array<string, TaxonomyDefinition> */
    private array $taxonomies = [];

    private ?SegmentationDefinition $segments = null;

    private ?IdentityDefinition $identity = null;

    private array $with = [];

    private ?Closure $mutateWritePayloadUsing = null;

    private ?Closure $toPayloadUsing = null;

    private ?Closure $resolveModelUsing = null;

    private ?Closure $persistUsing = null;

    protected function __construct(
        private readonly string $name,
    ) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function model(string $modelClass): static
    {
        $this->modelClass = $modelClass;

        foreach ($this->facets as $facet) {
            $facet->owningModelClass($modelClass);
        }

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function pluralLabel(string $pluralLabel): static
    {
        $this->pluralLabel = $pluralLabel;

        return $this;
    }

    public function identity(IdentityDefinition|callable|null $configure = null): IdentityDefinition|static
    {
        if ($configure instanceof IdentityDefinition) {
            $this->identity = $configure;

            return $this;
        }

        $this->identity ??= new Identity;

        if ($configure === null) {
            return $this->identity;
        }

        $configure($this->identity);

        return $this;
    }

    public function with(array $relations): static
    {
        $this->with = array_values($relations);

        return $this;
    }

    public function mutateWritePayloadUsing(Closure $closure): static
    {
        $this->mutateWritePayloadUsing = $closure;

        return $this;
    }

    public function toPayloadUsing(Closure $closure): static
    {
        $this->toPayloadUsing = $closure;

        return $this;
    }

    public function resolveModelUsing(Closure $closure): static
    {
        $this->resolveModelUsing = $closure;

        return $this;
    }

    public function persistUsing(Closure $closure): static
    {
        $this->persistUsing = $closure;

        return $this;
    }

    public function facet(FacetDefinition|string $name, string|callable|null $binding = null, ?callable $configure = null, ?string $as = null): FacetDefinition|static
    {
        if ($name instanceof FacetDefinition) {
            $name->owningModelClass($this->modelClass);
            $closure = is_callable($binding) ? $binding : $configure;

            if ($closure !== null) {
                $closure($name);
            }

            $this->facets[$name->name()] = $name;

            return $this;
        }

        if (class_exists($name) && (is_callable([$name, 'make']) || is_callable([$name, 'facet']))) {
            $closure = is_callable($binding) ? $binding : $configure;
            $facetName = $as;
            $dataPath = is_string($binding) ? $binding : null;

            /** @var FacetDefinition $facet */
            $facet = is_callable([$name, 'make'])
                ? $name::make($facetName, $dataPath)
                : $name::facet($facetName, $dataPath);
            $facet->owningModelClass($this->modelClass);
            $this->facets[$facet->name()] = $facet;

            if ($closure !== null) {
                $closure($facet);
            }

            return $this;
        }

        if (is_string($binding) && str_contains($name, '.')) {
            $facet = $this->importFacet($name, $binding, $as);

            if ($configure !== null) {
                $configure($facet);
            }

            return $this;
        }

        $facet = $this->facets[$name] ??= new Facet($name);
        $facet->owningModelClass($this->modelClass);

        $closure = is_callable($binding) ? $binding : $configure;

        if ($closure === null) {
            return $facet;
        }

        $closure($facet);

        return $this;
    }

    public function importFacet(string $source, string $binding, ?string $as = null): FacetDefinition
    {
        if (! str_contains($source, '.')) {
            throw new InvalidArgumentException("Imported facet source [{$source}] must be in [entity.facet] format.");
        }

        [$sourceEntity, $sourceFacet] = explode('.', $source, 2);
        $localName = $as ?? Str::snake(str_contains($binding, '.') ? Str::afterLast($binding, '.') : $binding);
        $localDataPath = str_starts_with($binding, 'data.') ? $binding : 'data.'.$binding;

        $facet = $this->facets[$localName] ??= Facet::make($localName);
        $facet->owningModelClass($this->modelClass);
        $facet->dataPath($localDataPath);

        $this->facetImports[$localName] = new FacetImportDefinition(
            $sourceEntity,
            $sourceFacet,
            $localName,
            $localDataPath,
            null,
        );

        return $facet;
    }

    public function facets(array $facets): static
    {
        foreach ($facets as $facet) {
            $this->facet($facet);
        }

        return $this;
    }

    public function view(ViewDefinition|string $name, ?callable $configure = null): ViewDefinition|static
    {
        if ($name instanceof ViewDefinition) {
            $this->views[$name->name()] = $name;

            return $this;
        }

        $view = $this->views[$name] ??= new View($name);

        if ($configure === null) {
            return $view;
        }

        $configure($view);

        return $this;
    }

    public function scope(ScopeDefinition|string $scope, ?callable $configure = null): ScopeDefinition|static
    {
        if ($scope instanceof ScopeDefinition) {
            $this->scopes[$scope->key()] = $scope;

            return $this;
        }

        $definition = $this->scopes[$scope] ??= ScopeDefinition::make($scope);

        if ($configure === null) {
            return $definition;
        }

        $configure($definition);

        return $this;
    }

    public function taxonomy(TaxonomyDefinition|string $taxonomy, ?callable $configure = null): TaxonomyDefinition|static
    {
        if ($taxonomy instanceof TaxonomyDefinition) {
            $this->taxonomies[$taxonomy->key()] = $taxonomy;

            return $this;
        }

        $definition = $this->taxonomies[$taxonomy] ??= TaxonomyDefinition::make($taxonomy);

        if ($configure === null) {
            return $definition;
        }

        $configure($definition);

        return $this;
    }

    public function segments(SegmentationDefinition|callable|null $configure = null): SegmentationDefinition|static
    {
        if ($configure instanceof SegmentationDefinition) {
            $this->segments = $configure;

            return $this;
        }

        $this->segments ??= SegmentationDefinition::make();

        if ($configure === null) {
            return $this->segments;
        }

        $configure($this->segments);

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

    public function toDefinitionData(): SchemaDefinitionData
    {
        return new SchemaDefinitionData(
            key: $this->name(),
            label: $this->getLabel(),
            pluralLabel: $this->getPluralLabel(),
            modelClass: $this->getModelClass(),
            facets: array_values(array_map(
                static fn (FacetDefinition $facet): FacetDefinitionData => $facet->toDefinitionData(),
                $this->getFacets(),
            )),
            meta: $this->allMeta(),
        );
    }

    public function getModelClass(): ?string
    {
        return $this->modelClass;
    }

    public function getLabel(): ?string
    {
        return $this->label ?? Str::headline($this->name);
    }

    public function getPluralLabel(): ?string
    {
        return $this->pluralLabel ?? Str::plural($this->getLabel() ?? Str::headline($this->name));
    }

    public function getIdentity(): ?IdentityDefinition
    {
        return $this->identity;
    }

    public function getWith(): array
    {
        return $this->with;
    }

    public function getMutateWritePayloadUsing(): ?Closure
    {
        return $this->mutateWritePayloadUsing;
    }

    public function getToPayloadUsing(): ?Closure
    {
        return $this->toPayloadUsing;
    }

    public function getResolveModelUsing(): ?Closure
    {
        return $this->resolveModelUsing;
    }

    public function getPersistUsing(): ?Closure
    {
        return $this->persistUsing;
    }

    /**
     * @return array<string, FacetDefinition>
     */
    public function getFacets(): array
    {
        return $this->facets;
    }

    /**
     * @return array<string, FacetImportDefinition>
     */
    public function getFacetImports(): array
    {
        return $this->facetImports;
    }

    /**
     * @param  array<string, FacetDefinition>  $facets
     */
    public function replaceFacets(array $facets): static
    {
        $this->facets = $facets;

        return $this;
    }

    public function clearFacetImports(): static
    {
        $this->facetImports = [];

        return $this;
    }

    /**
     * @return array<string, ViewDefinition>
     */
    public function getViews(): array
    {
        return $this->views;
    }

    /**
     * @return array<string, ScopeDefinition>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function requireScope(string $key): ScopeDefinition
    {
        return $this->scopes[$key]
            ?? throw new InvalidArgumentException("Scope [{$key}] is not defined for entity [{$this->name}].");
    }

    /**
     * @return array<string, TaxonomyDefinition>
     */
    public function getTaxonomies(): array
    {
        return $this->taxonomies;
    }

    public function getSegmentation(): ?SegmentationDefinition
    {
        return $this->segments;
    }

    public function requireTaxonomy(string $key): TaxonomyDefinition
    {
        return $this->taxonomies[$key]
            ?? throw new InvalidArgumentException("Taxonomy [{$key}] is not defined for entity [{$this->name}].");
    }

    public function requireFacet(string $name): FacetDefinition
    {
        return $this->facets[$name]
            ?? throw new InvalidArgumentException("Facet [{$name}] is not defined on entity [{$this->name}].");
    }

    public function requireView(string $name): ViewDefinition
    {
        return $this->views[$name]
            ?? throw new InvalidArgumentException("View [{$name}] is not defined on entity [{$this->name}].");
    }

    /**
     * @return array<string, FieldDefinition>
     */
    public function getFieldDefinitions(): array
    {
        $definitions = [];

        foreach ($this->facets as $facet) {
            foreach ($facet->getFieldDefinitions() as $name => $definition) {
                if (array_key_exists($name, $definitions)) {
                    continue;
                }

                $definitions[$name] = $definition;
            }
        }

        return $definitions;
    }

    public function requireFieldDefinition(string $name): FieldDefinition
    {
        if (str_contains($name, '.')) {
            [$facetName, $fieldName] = explode('.', $name, 2);

            return $this->requireFacetFieldDefinition($facetName, $fieldName);
        }

        return $this->getFieldDefinitions()[$name]
            ?? throw new InvalidArgumentException("Field [{$name}] is not defined on entity [{$this->name}].");
    }

    public function requireFacetFieldDefinition(string $facetName, string $fieldName): FieldDefinition
    {
        $facet = $this->requireFacet($facetName);

        return $facet->getFieldDefinitions()[$fieldName]
            ?? throw new InvalidArgumentException("Field [{$fieldName}] is not defined on facet [{$facetName}] for entity [{$this->name}].");
    }
}
