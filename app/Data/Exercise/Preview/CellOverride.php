<?php

namespace App\Data\Exercise\Preview;

use Coda\Cms\Data\AbstractData;

class CellOverride extends AbstractData
{
    public function __construct(
        public int $week,
        public int $session,
        public int $set,
        /** @var array<string, mixed> */
        public array $data = [],
    ) {}
}
