<?php

namespace App\Data\Exercise\Preview;

use App\Cms\Data\AbstractData;

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
        /** @var array{starting1RM: float, target1RM: float, targetGoal: int}|null */
        public ?array $summary = null,
        public int $sessionsPerWeek = 1,
    ) {}
}
