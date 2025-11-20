<?php

namespace App\Data\NavigationTree;

use App\Data\AbstractData;

class NavigationItem extends AbstractData
{
    public function __construct(
        public string $uuid,
        public string $title,
        public ?string $icon = null,
        public array $children = [],
        public string $type = 'item',
    ) {}
}
