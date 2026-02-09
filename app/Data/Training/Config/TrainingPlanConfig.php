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
        return $this->extractOverridesFromExercises($this->default->exercises, 'cells', $exerciseId);
    }

    public function userCellOverrides(int $userId, ?int $exerciseId = null): array
    {
        $user = $this->forUser($userId);

        if ($user === null) {
            return [];
        }

        return $this->extractOverridesFromExercises($user->exercises, 'cells', $exerciseId);
    }

    public function defaultWeekOverrides(?int $exerciseId = null): array
    {
        return $this->extractOverridesFromExercises($this->default->exercises, 'weeks', $exerciseId);
    }

    public function userWeekOverrides(int $userId, ?int $exerciseId = null): array
    {
        $user = $this->forUser($userId);

        if ($user === null) {
            return [];
        }

        return $this->extractOverridesFromExercises($user->exercises, 'weeks', $exerciseId);
    }

    public function defaultExerciseConfig(): array
    {
        if ($this->default->exerciseConfig instanceof Optional) {
            return [];
        }

        $strength = $this->default->exerciseConfig->strength_automatic;

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

        $strength = $user->exerciseConfig->strength_automatic;

        if ($strength === null) {
            return [];
        }

        return array_filter([
            'measuredReps' => $strength->measuredReps,
            'measuredWeight' => $strength->measuredWeight,
            'targetGoal' => $strength->targetGoal,
        ], fn ($v) => $v !== null);
    }

    // --- Write Accessors ---

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

    // --- Helpers ---

    protected function extractOverridesFromExercises(array $exercises, string $key, ?int $exerciseId = null): array
    {
        if ($exerciseId !== null) {
            foreach ($exercises as $exercise) {
                $id = $exercise instanceof Data ? $exercise->id : ($exercise['id'] ?? null);
                if ($id === $exerciseId) {
                    $overrides = $exercise instanceof Data ? $exercise->overrides : ($exercise['overrides'] ?? []);

                    return $overrides[$key] ?? [];
                }
            }

            return [];
        }

        $result = [];
        foreach ($exercises as $exercise) {
            $id = $exercise instanceof Data ? $exercise->id : ($exercise['id'] ?? null);
            $overrides = $exercise instanceof Data ? $exercise->overrides : ($exercise['overrides'] ?? []);
            if ($id !== null && ! empty($overrides[$key])) {
                $result[$id] = $overrides[$key];
            }
        }

        return $result;
    }

    protected function toPlainArrays(array $items): array
    {
        return array_map(
            fn ($item) => $item instanceof Data ? $item->toArray() : $item,
            $items
        );
    }
}
