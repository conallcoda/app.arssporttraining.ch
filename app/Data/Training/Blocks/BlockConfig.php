<?php

namespace App\Data\Training\Blocks;

use Coda\Cms\Data\AbstractData;

class BlockConfig extends AbstractData
{
    public function __construct(
        public ?int $goal = null,
        public bool $autoRecord1rm = false,
    ) {}
}
