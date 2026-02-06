<?php

namespace App\Data\Training\Config;

use App\Cms\Data\AbstractConfig;
use App\Data\Training\Config\Exercise\DefaultExerciseConfig;
use App\Data\Training\Config\Schedule\DefaultScheduleConfig;

class DefaultTrainingPlanConfig extends AbstractConfig
{
    public function __construct(
        public ?DefaultExerciseConfig $exerciseConfig = null,
        public ?DefaultScheduleConfig $schedule = null,
    ) {}
}
