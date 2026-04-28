<?php

namespace App\Data\Exercise\Preview;

use Coda\Cms\Data\AbstractData;

class PreviewGrid extends AbstractData
{
    public function __construct(
        /** @var PreviewGridRow[] */
        public array $rows,
        public int $weekCount,
        public int $setCount,
        public string $setLabel = 'Set',
        /** @var PreviewGridRow[] */
        public array $weekColumns = [],
        /** @var array{starting1RM: float, target1RM: float, modifier: int, targetGoal: int}|null */
        public ?array $summary = null,
        public int $sessionsPerWeek = 1,
        /** @var array<int, int> */
        public array $weekSessionCounts = [],
    ) {}
}
