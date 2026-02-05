<?php

namespace App\Data\Training\Config;

use App\Cms\Data\AbstractConfig;

class AthleteTrainingTarget extends AbstractConfig
{
    public function __construct(
        public ?int $measuredReps = null,
        public ?float $measuredWeight = null,
        public ?int $targetGoal = null,
    ) {}
}
