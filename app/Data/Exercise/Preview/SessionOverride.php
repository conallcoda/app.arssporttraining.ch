<?php

namespace App\Data\Exercise\Preview;

use Coda\Cms\Data\AbstractData;

class SessionOverride extends AbstractData
{
    public function __construct(
        public int $week,
        public int $session,
        /** @var array<string, mixed> */
        public array $data = [],
    ) {}
}
