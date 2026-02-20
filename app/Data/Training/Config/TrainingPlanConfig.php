<?php

namespace App\Data\Training\Config;

use App\Data\Training\Config\Schedule\DefaultScheduleConfig;
use App\Data\Training\Config\Target\TargetConfig;
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
        $weeks = array_map(fn (int $i) => [
            'id' => "default_{$i}",
            'linkedTo' => $i > 0 ? 'default_0' : null,
            'sort' => $i,
            'slots' => [],
        ], range(0, 4));

        return self::from([
            'default' => [
                'schedule' => [
                    'weeks' => $weeks,
                    'startDate' => '',
                ],
                'target' => [
                    'measuredReps' => 1,
                    'measuredWeight' => 50,
                    'targetGoal' => 10,
                ],
            ],
        ]);
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

    public function defaultTarget(): ?TargetConfig
    {
        if ($this->default->target instanceof Optional) {
            return null;
        }

        return $this->default->target;
    }

    public function defaultTargetMeasuredReps(): ?int
    {
        return $this->defaultTarget()?->measuredReps;
    }

    public function defaultTargetMeasuredWeight(): ?float
    {
        return $this->defaultTarget()?->measuredWeight;
    }

    public function defaultTargetGoal(): int|float
    {
        return $this->defaultTarget()?->targetGoal ?? 10;
    }

    public function userTargetMeasuredReps(int $userId): ?int
    {
        $user = $this->forUser($userId);

        if ($user === null || $user->target instanceof Optional) {
            return null;
        }

        return $user->target->measuredReps;
    }

    public function userTargetMeasuredWeight(int $userId): ?float
    {
        $user = $this->forUser($userId);

        if ($user === null || $user->target instanceof Optional) {
            return null;
        }

        return $user->target->measuredWeight;
    }

    public function userTargetGoal(int $userId): int|float
    {
        $user = $this->forUser($userId);

        if ($user === null || $user->target instanceof Optional) {
            return $this->defaultTargetGoal();
        }

        return $user->target->targetGoal ?? $this->defaultTargetGoal();
    }

    public function setDefaultTarget(?int $measuredReps, ?float $measuredWeight, int|float $targetGoal): void
    {
        $this->default->target = TargetConfig::from([
            'measuredReps' => $measuredReps,
            'measuredWeight' => $measuredWeight,
            'targetGoal' => $targetGoal,
        ]);
    }

    public function setUserTarget(int $userId, ?int $measuredReps, ?float $measuredWeight, int|float $targetGoal): void
    {
        $user = $this->forUser($userId);

        if ($user === null) {
            $this->users[$userId] = UserTrainingPlanConfig::from([
                'target' => [
                    'measuredReps' => $measuredReps,
                    'measuredWeight' => $measuredWeight,
                    'targetGoal' => $targetGoal,
                ],
            ]);
        } else {
            $user->target = TargetConfig::from([
                'measuredReps' => $measuredReps,
                'measuredWeight' => $measuredWeight,
                'targetGoal' => $targetGoal,
            ]);
        }
    }

    public function hasDefaultOverrides(): bool
    {
        $initialized = self::initialize();

        return json_encode($this->default->toArray()) !== json_encode($initialized->default->toArray());
    }

    public function hasUserOverrides(): bool
    {
        return ! empty($this->users);
    }

    public function resetDefaults(): void
    {
        $initialized = self::initialize();
        $this->default = $initialized->default;
    }

    public function resetAll(): void
    {
        $this->resetDefaults();
        $this->users = [];
    }

    protected function toPlainArrays(array $items): array
    {
        return array_map(
            fn ($item) => $item instanceof Data ? $item->toArray() : $item,
            $items
        );
    }
}
