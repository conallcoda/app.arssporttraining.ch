<?php

namespace App\Data\Training\Config\Exercise;

use App\Cms\Data\AbstractConfig;

class ExerciseOverride extends AbstractConfig
{
    public function __construct(
        public int $id,
        public ExerciseOverrideConfig $config,
    ) {}
}
