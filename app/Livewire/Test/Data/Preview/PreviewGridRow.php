<?php

namespace App\Livewire\Test\Data\Preview;

class PreviewGridRow
{
    public function __construct(
        public string $field,
        public string $label,
        public string $color,
        /** @var array<int, array<int, string|int|float>>|array<int, string|int|float> */
        public array $cells = [],
    ) {}
}
