<?php

namespace App\Data\Training\Config;

use App\Cms\Data\AbstractConfig;

class ResolvedTrainingTarget extends AbstractConfig
{
    public function __construct(
        public int $measuredReps,
        public float $measuredWeight,
        public int $targetGoal,
    ) {}
}
