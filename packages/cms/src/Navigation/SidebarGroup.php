<?php

namespace Coda\Cms\Navigation;

use Coda\Cms\Data\AbstractData;

class SidebarGroup extends AbstractData
{
    public function __construct(
        public string $key = '',
        public string $heading = '',
        public string $icon = '',
        /** @var SidebarItem[] */
        public array $items = [],
    ) {}

    public static function make(string $key, string $heading): static
    {
        return new static(key: $key, heading: $heading);
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function items(array $items): static
    {
        $this->items = $items;

        return $this;
    }
}
