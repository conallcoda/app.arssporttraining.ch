<?php

namespace App\Data\Training\Config\Exercise;

use App\Cms\Data\AbstractConfig;

class AthleteStrengthConfig extends AbstractConfig
{
    public function __construct(
        public ?int $measuredReps = null,
        public ?float $measuredWeight = null,
        public ?int $targetGoal = null,
    ) {}
}
