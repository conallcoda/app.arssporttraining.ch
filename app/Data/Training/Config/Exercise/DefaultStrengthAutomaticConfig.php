<?php

namespace App\Data\Training\Config\Exercise;

use App\Cms\Data\AbstractConfig;

class DefaultStrengthAutomaticConfig extends AbstractConfig
{
    public function __construct(
        public int $measuredReps = 8,
        public float $measuredWeight = 52,
        public int $targetGoal = 7,
    ) {}
}
