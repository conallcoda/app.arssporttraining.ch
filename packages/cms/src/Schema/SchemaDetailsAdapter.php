<?php

namespace Coda\Cms\Schema;

use Coda\Cms\Layout\Column;
use Coda\Cms\Layout\Fieldset as LayoutFieldset;
use Coda\Cms\Layout\Grid;
use Coda\Cms\Layout\Layout;
use Coda\Cms\Layout\Tab;
use Coda\SchemaKit\ResolvedViewDefinition;
use Coda\SchemaKit\SchemaRegistry;

class SchemaDetailsAdapter
{
    public function __construct(
        private readonly SchemaRegistry $registry,
    ) {}

    public function details(string $entityName, string $viewName): Layout
    {
        $resolved = $this->registry->resolveView($entityName, $viewName);
        $tabs = [];
        $details = $resolved->view()->getDetails();

        if ($details !== null) {
            foreach ($details->getTabs() as $tabDefinition) {
                $schema = $tabDefinition->getSchema();

                if ($schema !== []) {
                    $tabs[] = Tab::make($tabDefinition->title())->schema($schema);

                    continue;
                }

                $tabSchema = [];
                $infoBox = $tabDefinition->getInfoBox();

                if (is_callable($infoBox)) {
                    $infoBox = $infoBox();
                }

                if ($infoBox !== null) {
                    $tabSchema[] = $infoBox;
                }

                $left = $this->detailFieldsetsForNames($resolved, $tabDefinition->getLeft());
                $right = $this->detailFieldsetsForNames($resolved, $tabDefinition->getRight());
                $columns = [];

                if ($left !== []) {
                    $columns[] = Column::make($right === [] ? 8 : 8)->schema($left);
                }

                if ($right !== []) {
                    $columns[] = Column::make(4)->schema($right);
                }

                $tabSchema[] = Grid::make(12)->schema($columns);
                $tabs[] = Tab::make($tabDefinition->title())->schema($tabSchema);
            }

            return Layout::make()
                ->none()
                ->tabs($tabs);
        }

        foreach ($resolved->view()->getMeta('details_tabs', []) as $tabConfig) {
            if (! is_array($tabConfig) || ! isset($tabConfig['title'])) {
                continue;
            }

            if (array_key_exists('schema', $tabConfig)) {
                $tabs[] = Tab::make((string) $tabConfig['title'])->schema(
                    is_array($tabConfig['schema']) ? $tabConfig['schema'] : []
                );

                continue;
            }

            $schema = [];
            $infoBox = $tabConfig['info_box'] ?? null;

            if (is_callable($infoBox)) {
                $infoBox = $infoBox();
            }

            if ($infoBox !== null) {
                $schema[] = $infoBox;
            }

            $left = $this->detailFieldsetsForNames($resolved, $tabConfig['left'] ?? []);
            $right = $this->detailFieldsetsForNames($resolved, $tabConfig['right'] ?? []);
            $columns = [];

            if ($left !== []) {
                $columns[] = Column::make($right === [] ? 8 : 8)->schema($left);
            }

            if ($right !== []) {
                $columns[] = Column::make(4)->schema($right);
            }

            $schema[] = Grid::make(12)->schema($columns);
            $tabs[] = Tab::make((string) $tabConfig['title'])->schema($schema);
        }

        return Layout::make()
            ->none()
            ->tabs($tabs);
    }

    /**
     * @param  array<int, string>  $facetNames
     * @return array<int, LayoutFieldset>
     */
    private function detailFieldsetsForNames(ResolvedViewDefinition $resolved, array $facetNames): array
    {
        $fieldsets = [];

        foreach ($facetNames as $facetName) {
            $facet = $resolved->facet($facetName);
            $fieldsetName = $facet->getDetails()?->getFieldset();

            if (! is_string($fieldsetName) || $fieldsetName === '') {
                $details = $facet->getMeta('details');
                $fieldsetName = is_array($details) && isset($details['fieldset'])
                    ? (string) $details['fieldset']
                    : $facet->name();
            }

            $fieldsets[] = LayoutFieldset::make($fieldsetName);
        }

        return $fieldsets;
    }
}
