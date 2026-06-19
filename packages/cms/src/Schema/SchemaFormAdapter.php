<?php

namespace Coda\Cms\Schema;

use Coda\Cms\Registry;
use Coda\FormKit\Field;
use Coda\FormKit\Form;
use Coda\SchemaKit\EntityDefinition;
use Coda\SchemaKit\FacetDefinition;
use Coda\SchemaKit\FieldDefinition;
use Coda\SchemaKit\SchemaRegistry;

class SchemaFormAdapter
{
    public function __construct(
        private readonly SchemaRegistry $registry,
        private readonly SchemaFormFieldFactory $fieldFactory,
    ) {}

    public function form(string $entityName, string $viewName): Form
    {
        $entity = $this->registry->entity($entityName);
        $resolved = $this->registry->resolveView($entityName, $viewName);
        $form = Form::make();

        foreach ($resolved->facets() as $facet) {
            $config = $facet->getForm();
            $legacyConfig = is_array($facet->getMeta('form')) ? $facet->getMeta('form') : [];

            $fields = $config?->getFields() ?? ($legacyConfig['fields'] ?? []);

            if (is_callable($fields)) {
                $resolver = $fields;
                $fields = static fn (array $data): array => [
                    'fields' => $resolver(),
                ];
            } elseif ($fields === []) {
                $fields = $this->formFieldsForFacet($entity, $facet);
            } elseif (is_array($fields) && $this->isFieldNameList($fields)) {
                $fields = $this->formFieldsForFacet($entity, $facet, $fields);
            }

            $form->fieldset(
                $config?->getLabel()
                    ?? ($legacyConfig['label'] ?? $facet->getLabel() ?? ucfirst(str_replace('_', ' ', $facet->name()))),
                is_array($fields) || is_callable($fields) ? $fields : [],
                prefix: $config?->getPrefix()
                    ?? $facet->getDataPath()
                    ?? (is_string($legacyConfig['prefix'] ?? null) ? $legacyConfig['prefix'] : null),
                view: $config?->getView() ?? ($legacyConfig['view'] ?? null),
                viewData: $config?->getViewData() ?: (is_array($legacyConfig['viewData'] ?? null) ? $legacyConfig['viewData'] : []),
                layout: $config?->getLayout(),
                name: $config?->getFieldset() ?? $facet->name(),
            );
        }

        $tabs = $resolved->view()->getMeta('form_tabs', []);

        if ((! is_array($tabs) || $tabs === []) && collect($resolved->facets())->contains(fn (FacetDefinition $facet) => is_string($facet->getForm()?->getTab()) && $facet->getForm()?->getTab() !== '')) {
            $tabs = array_map(
                fn (FacetDefinition $facet) => $facet->getForm()?->getTab()
                    ?? ($facet->getForm()?->getLabel() ?? $facet->getLabel() ?? ucfirst(str_replace('_', ' ', $facet->name()))),
                $resolved->facets(),
            );
        }

        if (is_array($tabs) && $tabs !== []) {
            $form->fieldsetTabs($tabs);
        }

        return $form;
    }

    /**
     * @return array<int, Field>
     */
    private function formFieldsForFacet(EntityDefinition $entity, FacetDefinition $facet, ?array $fieldNames = null): array
    {
        $fields = [];

        foreach ($fieldNames ?? $facet->getFields() as $name) {
            $definition = $facet->getFieldDefinitions()[$name] ?? null;

            if (! $definition instanceof FieldDefinition) {
                continue;
            }

            if (! $definition->isFormVisible() || (bool) $definition->getMeta('form', true) === false) {
                continue;
            }

            if ($this->shouldHideScopeField($entity, $definition)) {
                continue;
            }

            $fields[] = $this->fieldFactory->make($definition);
        }

        return $fields;
    }

    private function shouldHideScopeField(EntityDefinition $entity, FieldDefinition $definition): bool
    {
        foreach ($entity->getScopes() as $scope) {
            $field = $scope->getField();

            if (! is_string($field) || $field === '' || $field !== $definition->name()) {
                continue;
            }

            $value = app(Registry::class)->currentContextValue($scope->getContextPath());

            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    private function isFieldNameList(array $fields): bool
    {
        return $fields !== [] && collect($fields)->every(static fn ($field) => is_string($field));
    }
}
