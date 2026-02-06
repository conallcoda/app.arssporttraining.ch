<?php

namespace App\Data\Training\Config;

use App\Cms\Data\AbstractConfig;
use App\Data\Training\Config\Exercise\DefaultExerciseConfig;
use App\Data\Training\Config\Schedule\DefaultScheduleConfig;
use Spatie\LaravelData\Optional;

/**
 * @property array<int, \App\Data\Training\Config\Exercise\ExerciseOverride> $exercises
 */
class DefaultTrainingPlanConfig extends AbstractConfig
{
    public function __construct(
        public DefaultExerciseConfig|Optional $exerciseConfig,
        public DefaultScheduleConfig|Optional $schedule,
        public array $exercises = [],
    ) {}
}
