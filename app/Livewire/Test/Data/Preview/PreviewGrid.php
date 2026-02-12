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
        /** @var PreviewGridRow[] */
        public array $weekColumns = [],
        /** @var array{starting1RM: float, target1RM: float, targetGoal: int}|null */
        public ?array $summary = null,
        public int $sessionsPerWeek = 1,
    ) {}
}
