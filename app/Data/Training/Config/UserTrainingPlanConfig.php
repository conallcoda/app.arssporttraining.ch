<?php

namespace App\Data\Training\Config;

use App\Cms\Data\AbstractConfig;
use App\Data\Training\Config\Exercise\AthleteExerciseConfig;
use App\Data\Training\Config\Schedule\DefaultScheduleConfig;
use Spatie\LaravelData\Optional;

/**
 * @property array<int, \App\Data\Training\Config\Exercise\ExerciseOverride> $exercises
 * @property array<int, \App\Data\Training\Config\Cell\CellOverride[]> $cells
 */
class UserTrainingPlanConfig extends AbstractConfig
{
    public function __construct(
        public DefaultScheduleConfig|Optional $schedule,
        public AthleteExerciseConfig|Optional $exerciseConfig,
        public array $exercises = [],
        public array $cells = [],
    ) {}
}
