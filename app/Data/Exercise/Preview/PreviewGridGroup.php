<?php

namespace App\Data\Exercise\Preview;

use Coda\Cms\Data\AbstractData;

class PreviewGridGroup extends AbstractData
{
    /**
     * @param  PreviewGridGroupSession[]  $sessions
     * @param  string[]  $collapsedMetaLines
     */
    public function __construct(
        public int $index,
        public string $label,
        public int $sessionCount,
        public array $sessions = [],
        public string $sessionRangeLabel = '',
        public bool $expanded = false,
        public bool $hasLockedSessions = false,
        public array $collapsedMetaLines = [],
        public bool $showCopyMenu = false,
    ) {}
}
