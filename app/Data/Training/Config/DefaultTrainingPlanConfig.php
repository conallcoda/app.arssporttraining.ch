<?php

namespace App\Data\Training\Config;

use App\Data\Training\Config\Schedule\DefaultScheduleConfig;
use Coda\Cms\Data\AbstractConfig;
use Spatie\LaravelData\Optional;

class DefaultTrainingPlanConfig extends AbstractConfig
{
    public function __construct(
        public DefaultScheduleConfig|Optional $schedule,
    ) {}
}
