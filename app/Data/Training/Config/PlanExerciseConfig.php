<?php

namespace App\Data\Training\Config;

use Coda\Cms\Data\AbstractData;

class PlanExerciseConfig extends AbstractData
{
    public function __construct(
        public ExerciseOverrides $plan = new ExerciseOverrides,
        /** @var array<int, ExerciseOverrides> */
        public array $users = [],
    ) {}

    public function forUser(int $userId): ExerciseOverrides
    {
        $userData = $this->users[$userId] ?? null;

        if ($userData === null) {
            return new ExerciseOverrides;
        }

        if ($userData instanceof ExerciseOverrides) {
            return $userData;
        }

        $overrides = ExerciseOverrides::from($userData);
        $this->users[$userId] = $overrides;

        return $overrides;
    }

    public function setUserOverrides(int $userId, ExerciseOverrides $overrides): void
    {
        $this->users[$userId] = $overrides;
    }

    public function clearUserOverrides(int $userId): void
    {
        unset($this->users[$userId]);
    }
}
