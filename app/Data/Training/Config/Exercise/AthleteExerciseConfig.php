<?php

namespace App\Data\Training\Config\Exercise;

use App\Cms\Data\AbstractConfig;

class AthleteExerciseConfig extends AbstractConfig
{
    public function __construct(
        public ?AthleteStrengthAutomaticConfig $strength_automatic = null,
    ) {}
}
