<?php

namespace App\Livewire\Test\Data\Preview;

class PreviewGrid
{
    public function __construct(
        /** @var PreviewGridRow[] */
        public array $rows,
        public int $weekCount,
        public int $setCount,
        public string $setLabel = 'Set',
    ) {}
}
