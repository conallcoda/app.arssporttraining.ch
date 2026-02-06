<?php

namespace App\Data\Training\Config\Exercise;

use App\Cms\Data\AbstractConfig;

class DefaultExerciseConfig extends AbstractConfig
{
    public function __construct(
        public ?DefaultStrengthConfig $strength = null,
    ) {}
}
