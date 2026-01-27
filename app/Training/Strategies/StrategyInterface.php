<?php

namespace App\Training\Strategies;

use App\Training\Data\ExerciseConfig;
use App\Training\Data\TrainingBlock;

interface StrategyInterface
{
    public function generate(ExerciseConfig $config, int $weeks): TrainingBlock;
}
