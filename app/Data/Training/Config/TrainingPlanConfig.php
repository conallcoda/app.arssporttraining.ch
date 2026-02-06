<?php

namespace App\Data\Training\Config;

use App\Cms\Data\AbstractConfig;
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

    // --- Read Accessors ---

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

    public function defaultExerciseOverrides(): array
    {
        return $this->toPlainArrays($this->default->exercises);
    }

    public function userExerciseOverrides(int $userId): array
    {
        return $this->toPlainArrays($this->forUser($userId)?->exercises ?? []);
    }

    public function defaultCellOverrides(?int $exerciseId = null): array
    {
        if ($exerciseId !== null) {
            return $this->default->cells[$exerciseId] ?? [];
        }

        return $this->default->cells;
    }

    public function userCellOverrides(int $userId, ?int $exerciseId = null): array
    {
        $user = $this->forUser($userId);

        if ($user === null) {
            return [];
        }

        if ($exerciseId !== null) {
            return $user->cells[$exerciseId] ?? [];
        }

        return $user->cells;
    }

    public function defaultWeekOverrides(?int $exerciseId = null): array
    {
        if ($exerciseId !== null) {
            return $this->default->weeks[$exerciseId] ?? [];
        }

        return $this->default->weeks;
    }

    public function userWeekOverrides(int $userId, ?int $exerciseId = null): array
    {
        $user = $this->forUser($userId);

        if ($user === null) {
            return [];
        }

        if ($exerciseId !== null) {
            return $user->weeks[$exerciseId] ?? [];
        }

        return $user->weeks;
    }

    public function defaultExerciseConfig(): array
    {
        if ($this->default->exerciseConfig instanceof Optional) {
            return [];
        }

        $strength = $this->default->exerciseConfig->strength;

        if ($strength === null) {
            return [];
        }

        return [
            'measuredReps' => $strength->measuredReps,
            'measuredWeight' => $strength->measuredWeight,
            'targetGoal' => $strength->targetGoal,
        ];
    }

    public function userExerciseConfig(int $userId): array
    {
        $user = $this->forUser($userId);

        if ($user === null || $user->exerciseConfig instanceof Optional) {
            return [];
        }

        $strength = $user->exerciseConfig->strength;

        if ($strength === null) {
            return [];
        }

        return array_filter([
            'measuredReps' => $strength->measuredReps,
            'measuredWeight' => $strength->measuredWeight,
            'targetGoal' => $strength->targetGoal,
        ], fn ($v) => $v !== null);
    }

    // --- Helpers ---

    protected function toPlainArrays(array $items): array
    {
        return array_map(
            fn ($item) => $item instanceof Data ? $item->toArray() : $item,
            $items
        );
    }
}
