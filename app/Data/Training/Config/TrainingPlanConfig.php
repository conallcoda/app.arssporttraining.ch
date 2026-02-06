<?php

namespace App\Data\Training\Config;

use App\Cms\Data\AbstractConfig;

class TrainingPlanConfig extends AbstractConfig
{
    public function __construct(
        public DefaultTrainingPlanConfig $default,
        public UsersTrainingPlanConfig $users
    ) {}
}
