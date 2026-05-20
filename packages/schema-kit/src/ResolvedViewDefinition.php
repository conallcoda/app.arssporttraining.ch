<?php

namespace Coda\SchemaKit;

use InvalidArgumentException;

final class ResolvedViewDefinition
{
    /**
        * @param  array<int, FacetDefinition>  $facets
        */
    public function __construct(
        private readonly EntityDefinition $entity,
        private readonly ViewDefinition $view,
        private readonly array $facets,
    ) {}

    public function entity(): EntityDefinition
    {
        return $this->entity;
    }

    public function view(): ViewDefinition
    {
        return $this->view;
    }

    public function identity(): ?IdentityDefinition
    {
        return $this->entity->getIdentity();
    }

    /**
     * @return array<int, FacetDefinition>
     */
    public function facets(): array
    {
        return $this->facets;
    }

    public function facet(string $name): FacetDefinition
    {
        foreach ($this->facets as $facet) {
            if ($facet->name() === $name) {
                return $facet;
            }
        }

        throw new InvalidArgumentException("Facet [{$name}] is not included in view [{$this->view->name()}].");
    }

    public function field(string $name): FieldDefinition
    {
        if (str_contains($name, '.')) {
            [$facetName, $fieldName] = explode('.', $name, 2);
            $facet = $this->facet($facetName);

            return $facet->getFieldDefinitions()[$fieldName]
                ?? throw new InvalidArgumentException("Field [{$fieldName}] is not defined on facet [{$facetName}] in view [{$this->view->name()}].");
        }

        return $this->entity->requireFieldDefinition($name);
    }

    public function fieldKey(string $name): string
    {
        return $this->field($name)->name();
    }
}
