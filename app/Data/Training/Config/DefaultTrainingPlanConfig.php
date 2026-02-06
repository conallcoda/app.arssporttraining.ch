<?php

namespace App\Data\Training\Config;

use App\Cms\Data\AbstractConfig;
use App\Data\Training\Config\Exercise\DefaultExerciseConfig;
use App\Data\Training\Config\Schedule\DefaultScheduleConfig;
use Spatie\LaravelData\Optional;

/**
 * @property array<int, \App\Data\Training\Config\Exercise\ExerciseOverride> $exercises
 * @property array<int, \App\Data\Training\Config\Cell\CellOverride[]> $cells
 * @property array<int, array<string, array{tut?: string, rest?: int}>> $weeks
 */
class DefaultTrainingPlanConfig extends AbstractConfig
{
    public function __construct(
        public DefaultExerciseConfig|Optional $exerciseConfig,
        public DefaultScheduleConfig|Optional $schedule,
        public array $exercises = [],
        public array $cells = [],
        public array $weeks = [],
    ) {}
}
