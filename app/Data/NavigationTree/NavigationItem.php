<?php

namespace App\Data\NavigationTree;

use App\Data\AbstractData;

class NavigationItem extends AbstractData
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $icon = null,
        public string $badge,
        public array $children = [],

    ) {}
}
