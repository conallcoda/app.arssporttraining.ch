<?php

namespace Coda\Cms\Navigation;

use Coda\Cms\Data\AbstractData;

class Crumb extends AbstractData
{
    public function __construct(
        public string $label = '',
        public ?string $href = null,
        public bool $current = false,
    ) {}

    public static function make(string $label, ?string $href = null, bool $current = false): static
    {
        return new static(label: $label, href: $href, current: $current);
    }
}
