<?php

namespace App\Data\Exercise\Preview;

use Coda\Cms\Data\AbstractData;

class WeekOverride extends AbstractData
{
    public function __construct(
        public int $week,
        /** @var array<string, mixed> */
        public array $data = [],
    ) {}
}
