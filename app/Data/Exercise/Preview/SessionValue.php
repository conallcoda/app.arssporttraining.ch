<?php

namespace App\Data\Exercise\Preview;

use Coda\Cms\Data\AbstractData;

class SessionValue extends AbstractData
{
    public function __construct(
        public int $week,
        public int $session,
        /** @var array<string, mixed> */
        public array $values = [],
        /** @var array<int, array<string, mixed>> */
        public array $sets = [],
    ) {}
}
