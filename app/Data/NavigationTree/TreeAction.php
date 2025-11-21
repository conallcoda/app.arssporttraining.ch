<?php

namespace App\Data\NavigationTree;

use App\Data\AbstractData;

class TreeAction extends AbstractData
{
    public function __construct(
        public string $name,
        public string $label,
        public ?string $icon = null,
        public ?string $variant = null,
        public bool $separator = false,
        public bool $disabled = false,
        public array $metadata = [],
    ) {}

    public function type(): string
    {
        return explode('.', $this->name)[0];
    }

    public function action(): string
    {
        return explode('.', $this->name)[1];
    }
}
