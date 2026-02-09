<?php

namespace App\Data\Training\Config\Exercise;

use App\Cms\Data\AbstractConfig;
use Spatie\LaravelData\Optional;

class ExerciseOverrideConfig extends AbstractConfig
{
    public function __construct(
        public int|Optional $target,
        public int|Optional $startingReps,
        public int|Optional $sets,
        public string|Optional $tempo,
        public int|Optional $rest,
    ) {}
}
