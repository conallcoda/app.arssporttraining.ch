<?php

namespace Coda\Cms\Navigation;

use Coda\Cms\Data\AbstractData;

class Tab extends AbstractData
{
    public function __construct(
        public string $label = '',
        public string $route = '',
    ) {}

    public static function make(string $label, string $route): static
    {
        return new static(label: $label, route: $route);
    }
}
