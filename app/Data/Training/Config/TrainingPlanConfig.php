<?php

namespace App\Data\Training\Config;

use App\Cms\Data\AbstractConfig;
use App\Data\Training\Config\Schedule\DefaultScheduleConfig;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * @property array<int, UserTrainingPlanConfig> $users
 */
class TrainingPlanConfig extends AbstractConfig
{
    public function __construct(
        public DefaultTrainingPlanConfig $default,
        public array $users = [],
    ) {}

    public static function initialize(): self
    {
        return self::from(['default' => []]);
    }

    public function forUser(int $userId): ?UserTrainingPlanConfig
    {
        $userData = $this->users[$userId] ?? null;

        if ($userData === null) {
            return null;
        }

        if ($userData instanceof UserTrainingPlanConfig) {
            return $userData;
        }

        $config = UserTrainingPlanConfig::from($userData);
        $this->users[$userId] = $config;

        return $config;
    }

    public function defaultScheduleWeeks(): array
    {
        if ($this->default->schedule instanceof Optional) {
            return [];
        }

        return $this->toPlainArrays($this->default->schedule->weeks ?? []);
    }

    public function defaultScheduleStartDate(): ?string
    {
        if ($this->default->schedule instanceof Optional) {
            return null;
        }

        return $this->default->schedule->startDate ?: null;
    }

    public function userScheduleWeeks(int $userId): array
    {
        $user = $this->forUser($userId);

        if ($user === null || $user->schedule instanceof Optional) {
            return [];
        }

        return $this->toPlainArrays($user->schedule->weeks ?? []);
    }

    public function userScheduleStartDate(int $userId): ?string
    {
        $user = $this->forUser($userId);

        if ($user === null || $user->schedule instanceof Optional) {
            return null;
        }

        return $user->schedule->startDate ?: null;
    }

    public function setDefaultScheduleWeeks(array $weeks): void
    {
        $this->default->schedule = DefaultScheduleConfig::from([
            'weeks' => $weeks,
            'startDate' => $this->default->schedule instanceof Optional ? '' : $this->default->schedule->startDate,
        ]);
    }

    public function setUserScheduleWeeks(int $userId, array $weeks): void
    {
        $user = $this->forUser($userId);

        if ($user === null) {
            $this->users[$userId] = UserTrainingPlanConfig::from(['schedule' => ['weeks' => $weeks]]);
        } else {
            $user->schedule = DefaultScheduleConfig::from([
                'weeks' => $weeks,
                'startDate' => $user->schedule instanceof Optional ? '' : $user->schedule->startDate,
            ]);
        }
    }

    protected function toPlainArrays(array $items): array
    {
        return array_map(
            fn ($item) => $item instanceof Data ? $item->toArray() : $item,
            $items
        );
    }
}
