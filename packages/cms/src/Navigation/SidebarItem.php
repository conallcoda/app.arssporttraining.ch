<?php

namespace Coda\Cms\Navigation;

use Coda\Cms\Data\AbstractData;

class SidebarItem extends AbstractData
{
    public function __construct(
        public string $label = '',
        public string $route = '',
        public string $icon = '',
        /** @var Tab[] */
        public array $tabs = [],
    ) {}

    public static function make(string $label, string $route): static
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
}
