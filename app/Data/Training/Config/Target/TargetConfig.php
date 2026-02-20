<?php

namespace App\Data\Training\Config\Target;

use Coda\Cms\Data\AbstractConfig;

class TargetConfig extends AbstractConfig
{
    public function __construct(
        public ?int $measuredReps = null,
        public ?float $measuredWeight = null,
        public int|float $targetGoal = 10,
    ) {}
}
