<?php

namespace App\Data\Training\Config;

use App\Data\Training\Config\Schedule\DefaultScheduleConfig;
use Coda\Cms\Data\AbstractConfig;
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

    public function isUserScheduleLocked(int $userId): bool
    {
        $user = $this->forUser($userId);

        if ($user === null || $user->schedule instanceof Optional) {
            return true;
        }

        return empty($user->schedule->weeks);
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
        if ($this->isUserScheduleLocked($userId)) {
            return $this->defaultScheduleStartDate();
        }

        $user = $this->forUser($userId);

        if ($user === null || $user->schedule instanceof Optional) {
            return $this->defaultScheduleStartDate();
        }

        return $user->schedule->startDate ?: $this->defaultScheduleStartDate();
    }

    public function setDefaultScheduleStartDate(string $startDate): void
    {
        $this->default->schedule = DefaultScheduleConfig::from([
            'weeks' => $this->default->schedule instanceof Optional ? [] : ($this->default->schedule->weeks ?? []),
            'startDate' => $startDate,
        ]);
    }

    public function setUserScheduleStartDate(int $userId, string $startDate): void
    {
        $user = $this->forUser($userId);

        if ($user === null) {
            $this->users[$userId] = UserTrainingPlanConfig::from(['schedule' => ['weeks' => [], 'startDate' => $startDate]]);
        } else {
            $user->schedule = DefaultScheduleConfig::from([
                'weeks' => $user->schedule instanceof Optional ? [] : ($user->schedule->weeks ?? []),
                'startDate' => $startDate,
            ]);
        }
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

    public function unlockUserSchedule(int $userId): void
    {
        $defaultWeeks = $this->defaultScheduleWeeks();
        $defaultStartDate = $this->defaultScheduleStartDate() ?? '';

        $this->users[$userId] = UserTrainingPlanConfig::from([
            'schedule' => [
                'weeks' => $defaultWeeks,
                'startDate' => $defaultStartDate,
            ],
        ]);
    }

    public function lockUserSchedule(int $userId): void
    {
        unset($this->users[$userId]);
    }

    protected function toPlainArrays(array $items): array
    {
        return array_map(
            fn ($item) => $item instanceof Data ? $item->toArray() : $item,
            $items
        );
    }
}
