<?php

namespace Coda\SchemaKit;

use InvalidArgumentException;

final class SchemaRegistry
{
    /** @var array<string, EntityDefinition> */
    private array $entities = [];

    /** @var array<string, EntityDefinition> */
    private array $resolvedEntities = [];

    public function register(EntityDefinition $entity): static
    {
        $this->entities[$entity->name()] = $entity;
        unset($this->resolvedEntities[$entity->name()]);

        return $this;
    }

    public function entity(string $name): EntityDefinition
    {
        $this->entities[$name]
            ?? throw new InvalidArgumentException("Entity [{$name}] is not registered.");

        return $this->resolvedEntities[$name] ??= $this->resolveEntity($name);
    }

    public function resolveView(string $entityName, string $viewName): ResolvedViewDefinition
    {
        $entity = $this->entity($entityName);
        $view = $entity->requireView($viewName);

        $facets = array_map(
            fn (string $facetName) => $entity->requireFacet($facetName),
            $view->getFacetNames(),
        );

        return new ResolvedViewDefinition($entity, $view, $facets);
    }

    /**
     * @return array<string, EntityDefinition>
     */
    public function all(): array
    {
        return array_map(
            fn (string $name) => $this->entity($name),
            array_keys($this->entities),
        );
    }

    private function resolveEntity(string $name, array $stack = []): EntityDefinition
    {
        if (in_array($name, $stack, true)) {
            throw new InvalidArgumentException('Circular schema facet import detected: '.implode(' -> ', [...$stack, $name]));
        }

        $entity = $this->entities[$name]
            ?? throw new InvalidArgumentException("Entity [{$name}] is not registered.");

        if ($entity->getFacetImports() === []) {
            return $entity;
        }

        $resolved = clone $entity;
        $facets = $entity->getFacets();

        foreach ($entity->getFacetImports() as $import) {
            $sourceEntity = $this->resolveEntity($import->sourceEntity(), [...$stack, $name]);
            $sourceFacet = $sourceEntity->requireFacet($import->sourceFacet());
            $localFacet = $facets[$import->localName()] ?? null;

            $facets[$import->localName()] = $this->importFacet(
                $sourceFacet,
                $import,
                $resolved->getModelClass(),
                $localFacet,
            );
        }

        return $resolved
            ->replaceFacets($facets)
            ->clearFacetImports();
    }

    private function importFacet(FacetDefinition $source, FacetImportDefinition $import, ?string $owningModelClass, ?FacetDefinition $local = null): FacetDefinition
    {
        $facet = Facet::make($import->localName())
            ->data($source->getDataClass(), $import->localDataPath())
            ->inferFields($source->shouldInferFields())
            ->owningModelClass($owningModelClass);

        if ($source->getLabel() !== null) {
            $facet->label($source->getLabel());
        }

        if ($source->getDescription() !== null) {
            $facet->description($source->getDescription());
        }

        if ($source->getStorage() !== null) {
            $facet->storage($source->getStorage());
        }

        if ($source->getApplicability() !== []) {
            $facet->applicability(...$source->getApplicability());
        }

        foreach ($source->allMeta() as $key => $value) {
            $facet->setMeta($key, $value);
        }

        $definitions = $source->getFieldDefinitions();

        foreach ($source->getFields() as $fieldName) {
            $definition = isset($definitions[$fieldName]) ? clone $definitions[$fieldName] : null;

            if ($definition === null) {
                continue;
            }

            $definition->setMeta('_allow_local_override', true);

            if ($definition instanceof ComputedDefinition) {
                $facet->defineComputed($definition);

                continue;
            }

            if ($definition instanceof RelationshipDefinition) {
                $facet->defineRelationship($definition);

                continue;
            }

            $facet->defineField($definition);
        }

        if (($form = $source->getForm()) !== null) {
            $form = clone $form;
            $form->fieldset($import->localName());
            $facet->form($form);
        }

        if (($details = $source->getDetails()) !== null) {
            $details = clone $details;
            $details->fieldset($import->localName());
            $facet->details($details);
        }

        if ($local !== null) {
            if ($local->getLabel() !== null) {
                $facet->label($local->getLabel());
            }

            if ($local->getDescription() !== null) {
                $facet->description($local->getDescription());
            }

            if ($local->getDataPath() !== null) {
                $facet->dataPath($local->getDataPath());
            }

            foreach ($local->allMeta() as $key => $value) {
                $facet->setMeta($key, $value);
            }

            if (($form = $local->getForm()) !== null) {
                $facet->form(clone $form);
            }

            if (($details = $local->getDetails()) !== null) {
                $facet->details(clone $details);
            }
        }

        if (($configure = $import->configure()) !== null) {
            $configure($facet);
        }

        return $facet;
    }
}
