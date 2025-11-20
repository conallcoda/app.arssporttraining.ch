<?php

namespace App\Data\NavigationTree;

use App\Data\AbstractData;

class NavigationItem extends AbstractData
{
    public function __construct(
        public string $name,
        public string $text,
        public ?string $icon = null,
        public bool $separator = false,
    ) {}
}
