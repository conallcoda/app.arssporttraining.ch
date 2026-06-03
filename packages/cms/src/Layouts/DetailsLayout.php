<?php

namespace Coda\Cms\Layouts;

final class DetailsLayout
{
    private array $facets = [];

    /** @var array<int, DetailsTab> */
    private array $tabs = [];

    public static function make(): static
    {
        return new static;
    }

    public function facets(array $facets): static
    {
        $this->facets = array_values($facets);

        return $this;
    }

    public function tab(DetailsTab $tab): static
    {
        $this->tabs[] = $tab;

        return $this;
    }

    public function tabs(array $tabs): static
    {
        $this->tabs = array_values($tabs);

        return $this;
    }

    public function getFacets(): array
    {
        return $this->facets;
    }

    /**
     * @return array<int, DetailsTab>
     */
    public function getTabs(): array
    {
        return $this->tabs;
    }
}
