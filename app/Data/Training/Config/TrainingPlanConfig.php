<?php

namespace App\Data\Training\Config;

use App\Cms\Data\AbstractConfig;

/**
 * @property array<int, UserTrainingPlanConfig> $users
 */
class TrainingPlanConfig extends AbstractConfig
{
    public function __construct(
        public DefaultTrainingPlanConfig $default,
        public array $users = [],
    ) {}

    public function forUser(int $userId): ?UserTrainingPlanConfig
    {
        $userData = $this->users[$userId] ?? null;

        if ($userData === null) {
            return null;
        }

        if ($userData instanceof UserTrainingPlanConfig) {
            return $userData;
        }

        return UserTrainingPlanConfig::from($userData);
    }
}
