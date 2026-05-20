<?php

namespace Coda\Cms\Layouts;

final class FormLayout
{
    /** @var array<int, FacetLayout> */
    private array $facetLayouts = [];

    private array $tabs = [];

    private ?string $modalWidth = null;

    public static function make(): static
    {
        return new static;
    }

    public function facet(
        FacetLayout|string $facet,
        ?string $label = null,
        ?array $fields = null,
        ?string $view = null,
        array $viewData = [],
        ?string $tab = null,
        ?string $fieldset = null,
        ?array $layout = null,
    ): static {
        $layout = $facet instanceof FacetLayout
            ? $facet
            : FacetLayout::make($facet)
                ->label($label)
                ->fields($fields)
                ->view($view)
                ->viewData($viewData)
                ->tab($tab)
                ->fieldset($fieldset)
                ->layout($layout);

        $this->facetLayouts[] = $layout;

        return $this;
    }

    public function facets(array $facets): static
    {
        $this->facetLayouts = [];

        foreach ($facets as $facet) {
            $this->facet($facet);
        }

        return $this;
    }

    public function tabs(array $tabs): static
    {
        $this->tabs = $tabs;

        return $this;
    }

    public function modalWidth(?string $modalWidth): static
    {
        $this->modalWidth = $modalWidth;

        return $this;
    }

    public function getFacets(): array
    {
        return array_map(
            static fn (FacetLayout $facet) => $facet->name(),
            $this->facetLayouts,
        );
    }

    /**
     * @return array<int, FacetLayout>
     */
    public function getFacetLayouts(): array
    {
        return $this->facetLayouts;
    }

    public function getTabs(): array
    {
        return $this->tabs;
    }

    public function getModalWidth(): ?string
    {
        return $this->modalWidth;
    }
}
