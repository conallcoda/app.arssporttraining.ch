<?php

namespace Coda\SchemaKit;

final class DetailsViewDefinition
{
    /** @var array<int, DetailsTabDefinition> */
    private array $tabs = [];

    public static function make(): static
    {
        return new static;
    }

    public function tabs(array $tabs): static
    {
        $this->tabs = array_values($tabs);

        return $this;
    }

    public function tab(DetailsTabDefinition $tab): static
    {
        $this->tabs[] = $tab;

        return $this;
    }

    /**
     * @return array<int, DetailsTabDefinition>
     */
    public function getTabs(): array
    {
        return $this->tabs;
    }
}
