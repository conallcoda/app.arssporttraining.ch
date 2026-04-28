<?php

namespace App\Data\Exercise\Preview;

use Coda\Cms\Data\AbstractData;

class PreviewGridWeek extends AbstractData
{
    /**
     * @param  int[]  $sessionNumbers
     * @param  bool[]  $lockedSessions
     * @param  string[]  $collapsedMetaLines
     * @param  int[]  $copyFromWeeks
     * @param  int[]  $copyToWeeks
     */
    public function __construct(
        public int $index,
        public string $label,
        public int $sessionCount,
        public array $sessionNumbers = [],
        public string $sessionRangeLabel = '',
        public bool $expanded = false,
        public array $lockedSessions = [],
        public bool $hasLockedSessions = false,
        public array $collapsedMetaLines = [],
        public bool $showCopyMenu = false,
        public array $copyFromWeeks = [],
        public array $copyToWeeks = [],
    ) {}
}
