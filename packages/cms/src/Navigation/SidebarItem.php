<?php

namespace Coda\Cms\Navigation;

use Coda\Cms\Data\AbstractData;

class SidebarItem extends AbstractData
{
    public function __construct(
        public string $label = '',
        public ?string $route = null,
        public string $icon = '',
        /** @var Tab[] */
        public array $tabs = [],
        /** @var SidebarItem[] */
        public array $items = [],
    ) {}

    public static function make(string $label, ?string $route = null): static
    {
        return new static(label: $label, route: $route);
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function tabs(array $tabs): static
    {
        $this->tabs = $tabs;

        return $this;
    }

    public function items(array $items): static
    {
        $this->items = $items;

        return $this;
    }
}
