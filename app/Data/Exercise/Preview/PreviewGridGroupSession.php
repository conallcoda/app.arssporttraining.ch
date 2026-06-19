<?php

namespace App\Data\Exercise\Preview;

use Coda\Cms\Data\AbstractData;

class PreviewGridGroupSession extends AbstractData
{
    public function __construct(
        public int $weekIndex,
        public int $sessionIndex,
        public int $sessionNumber,
        public bool $locked = false,
        public ?array $status = null,
    ) {}
}
