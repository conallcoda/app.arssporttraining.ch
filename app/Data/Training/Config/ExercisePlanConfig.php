<?php

namespace App\Data\Training\Config;

use App\Data\Exercise\ExerciseConfig;
use App\Data\Training\Config\Schedule\DefaultScheduleConfig;
use App\Data\Training\Config\Target\TargetConfig;
use Coda\Cms\Data\AbstractConfig;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class ExercisePlanConfig extends AbstractConfig
{
    public function __construct(
        public DefaultScheduleConfig|Optional $schedule,
        public TargetConfig|Optional $target,
        /** @var array<int, ExerciseOverrides> */
        public array $exercises = [],
        /** @var array<int, array<int, ExerciseOverrides>> */
        public array $userExercises = [],
        public int $weeks = 5,
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
            'schedule' => [
                'weeks' => $weeks,
            ],
            'target' => [
                'measuredReps' => 1,
                'measuredWeight' => 50,
                'targetGoal' => 10,
            ],
        ]);
    }

    public function defaultScheduleWeeks(): array
    {
        if ($this->schedule instanceof Optional) {
            return [];
        }

        return $this->toPlainArrays($this->schedule->weeks ?? []);
    }

    public function defaultScheduleStartDate(): ?string
    {
        return null;
    }

    public function setDefaultScheduleStartDate(string $startDate): void {}

    public function setDefaultScheduleWeeks(array $weeks): void
    {
        $this->schedule = DefaultScheduleConfig::from([
            'weeks' => $weeks,
        ]);
    }

    public function defaultTarget(): ?TargetConfig
    {
        if ($this->target instanceof Optional) {
            return null;
        }

        return $this->target;
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

    public function setDefaultTarget(?int $measuredReps, ?float $measuredWeight, int|float $targetGoal): void
    {
        $this->target = TargetConfig::from([
            'measuredReps' => $measuredReps,
            'measuredWeight' => $measuredWeight,
            'targetGoal' => $targetGoal,
        ]);
    }

    public function defaultExerciseOverrides(int $programExerciseId): ExerciseOverrides
    {
        $data = $this->exercises[$programExerciseId] ?? null;

        if ($data === null) {
            return new ExerciseOverrides;
        }

        if ($data instanceof ExerciseOverrides) {
            return $data;
        }

        $overrides = ExerciseOverrides::from($data);
        $this->exercises[$programExerciseId] = $overrides;

        return $overrides;
    }

    public function setDefaultExerciseOverrides(int $programExerciseId, ExerciseOverrides $overrides): void
    {
        $this->exercises[$programExerciseId] = $overrides;
    }

    public function removeExerciseOverrides(int $programExerciseId): void
    {
        unset($this->exercises[$programExerciseId]);
    }

    public function userExerciseOverrides(int $userId, int $programExerciseId): ExerciseOverrides
    {
        $data = $this->userExercises[$userId][$programExerciseId] ?? null;

        if ($data === null) {
            return new ExerciseOverrides;
        }

        if ($data instanceof ExerciseOverrides) {
            return $data;
        }

        $overrides = ExerciseOverrides::from($data);
        $this->userExercises[$userId][$programExerciseId] = $overrides;

        return $overrides;
    }

    public function exerciseOverrides(int $programExerciseId, ?int $userId = null): ExerciseOverrides
    {
        return $userId === null
            ? $this->defaultExerciseOverrides($programExerciseId)
            : $this->userExerciseOverrides($userId, $programExerciseId);
    }

    /** @return array{0: ExerciseOverrides, 1: ?ExerciseOverrides} */
    public function effectiveExerciseOverrides(int $programExerciseId, ?int $userId = null): array
    {
        $defaultOverrides = $this->defaultExerciseOverrides($programExerciseId);

        if ($userId === null) {
            return [$defaultOverrides, null];
        }

        return [$defaultOverrides, $this->userExerciseOverrides($userId, $programExerciseId)];
    }

    public function effectiveStartsAtDate(int $programExerciseId, ?int $userId = null): ?string
    {
        [$defaultOverrides, $userOverrides] = $this->effectiveExerciseOverrides($programExerciseId, $userId);

        return $userOverrides?->startsAtDate ?? $defaultOverrides->startsAtDate;
    }

    public function effectiveDisabled(int $programExerciseId, ?int $userId = null): bool
    {
        [$defaultOverrides, $userOverrides] = $this->effectiveExerciseOverrides($programExerciseId, $userId);

        return EffectiveExerciseConfig::resolveDisabled($defaultOverrides, $userOverrides);
    }

    public function resolveExercise(ExerciseConfig $baseConfig, int $programExerciseId, ?int $userId = null): ResolvedExerciseOverrides
    {
        [$defaultOverrides, $userOverrides] = $this->effectiveExerciseOverrides($programExerciseId, $userId);

        return new ResolvedExerciseOverrides(
            defaultOverrides: $defaultOverrides,
            userOverrides: $userOverrides,
            effectiveConfig: EffectiveExerciseConfig::resolve($baseConfig, $defaultOverrides, $userOverrides),
            overrideLayer: EffectiveExerciseConfig::resolveForLayer($baseConfig, $defaultOverrides, $userOverrides),
            effectiveStartsAtDate: $userOverrides?->startsAtDate ?? $defaultOverrides->startsAtDate,
            disabled: EffectiveExerciseConfig::resolveDisabled($defaultOverrides, $userOverrides),
        );
    }

    public function setUserExerciseOverrides(int $userId, int $programExerciseId, ExerciseOverrides $overrides): void
    {
        if (! isset($this->userExercises[$userId])) {
            $this->userExercises[$userId] = [];
        }

        $this->userExercises[$userId][$programExerciseId] = $overrides;
    }

    public function setExerciseOverrides(int $programExerciseId, ExerciseOverrides $overrides, ?int $userId = null): void
    {
        if ($userId === null) {
            $this->setDefaultExerciseOverrides($programExerciseId, $overrides);

            return;
        }

        $this->setUserExerciseOverrides($userId, $programExerciseId, $overrides);
    }

    public function removeUserExerciseOverrides(int $userId, int $programExerciseId): void
    {
        unset($this->userExercises[$userId][$programExerciseId]);

        if (($this->userExercises[$userId] ?? []) === []) {
            unset($this->userExercises[$userId]);
        }
    }

    public function removeExerciseOverridesForAllUsers(int $programExerciseId): void
    {
        foreach (array_keys($this->userExercises) as $userId) {
            $this->removeUserExerciseOverrides((int) $userId, $programExerciseId);
        }
    }

    /** @return array<int, array<int, ExerciseOverrides>> */
    public function allUserExerciseOverrides(): array
    {
        foreach (array_keys($this->userExercises) as $userId) {
            foreach (array_keys($this->userExercises[$userId] ?? []) as $programExerciseId) {
                $this->userExerciseOverrides((int) $userId, (int) $programExerciseId);
            }
        }

        return $this->userExercises;
    }

    public function remapUserExerciseOverrides(array $pivotIdMap): void
    {
        $remapped = [];

        foreach ($this->allUserExerciseOverrides() as $userId => $overridesByExercise) {
            foreach ($overridesByExercise as $programExerciseId => $overrides) {
                $newProgramExerciseId = $pivotIdMap[(int) $programExerciseId] ?? null;

                if ($newProgramExerciseId === null) {
                    continue;
                }

                $remapped[(int) $userId][(int) $newProgramExerciseId] = $overrides;
            }
        }

        $this->userExercises = $remapped;
    }

    public function remapDefaultExerciseOverrides(array $pivotIdMap): void
    {
        $remapped = [];

        foreach (array_keys($this->exercises) as $programExerciseId) {
            $newProgramExerciseId = $pivotIdMap[(int) $programExerciseId] ?? null;

            if ($newProgramExerciseId === null) {
                continue;
            }

            $remapped[(int) $newProgramExerciseId] = $this->defaultExerciseOverrides((int) $programExerciseId);
        }

        $this->exercises = $remapped;
    }

    public function copyMappedExerciseOverridesFrom(self $sourceConfig, array $pivotIdMap, ?string $startsAtDate = null): void
    {
        foreach ($pivotIdMap as $sourcePivotId => $targetPivotId) {
            if (isset($sourceConfig->exercises[(int) $sourcePivotId])) {
                $this->setDefaultExerciseOverrides(
                    (int) $targetPivotId,
                    $this->copyOverrides(
                        $sourceConfig->defaultExerciseOverrides((int) $sourcePivotId),
                        $startsAtDate,
                    ),
                );
            }
        }

        foreach ($sourceConfig->allUserExerciseOverrides() as $userId => $overridesByExercise) {
            foreach ($overridesByExercise as $sourcePivotId => $overrides) {
                $targetPivotId = $pivotIdMap[(int) $sourcePivotId] ?? null;

                if ($targetPivotId === null) {
                    continue;
                }

                $this->setUserExerciseOverrides(
                    (int) $userId,
                    (int) $targetPivotId,
                    $this->copyOverrides($overrides, $startsAtDate),
                );
            }
        }
    }

    public function clearStartsAtDates(): bool
    {
        $changed = false;

        foreach (array_keys($this->exercises) as $programExerciseId) {
            $overrides = $this->defaultExerciseOverrides((int) $programExerciseId);

            if ($overrides->startsAtDate !== null) {
                $overrides->startsAtDate = null;
                $changed = true;
            }
        }

        foreach ($this->allUserExerciseOverrides() as $userId => $overridesByExercise) {
            foreach (array_keys($overridesByExercise) as $programExerciseId) {
                $overrides = $this->userExerciseOverrides((int) $userId, (int) $programExerciseId);

                if ($overrides->startsAtDate !== null) {
                    $overrides->startsAtDate = null;
                    $changed = true;
                }
            }
        }

        return $changed;
    }

    public function hasDefaultOverrides(): bool
    {
        return ! empty($this->exercises);
    }

    public function resetDefaults(): void
    {
        $this->exercises = [];
    }

    public function resetAll(): void
    {
        $this->resetDefaults();
    }

    public function removeProgramFromAllSchedules(int $programId): void
    {
        $weeks = $this->stripProgramFromWeeks($this->defaultScheduleWeeks(), $programId);
        $this->setDefaultScheduleWeeks($weeks);
    }

    protected function stripProgramFromWeeks(array $weeks, int $programId): array
    {
        foreach ($weeks as &$week) {
            if (($week['linkedTo'] ?? null) !== null) {
                continue;
            }

            $slots = $week['slots'] ?? [];
            foreach ($slots as &$slot) {
                $slot['programs'] = array_values(array_filter(
                    $slot['programs'] ?? [],
                    fn (int $id) => $id !== $programId
                ));
            }

            $week['slots'] = array_values(array_filter($slots, fn (array $s) => ! empty($s['programs'] ?? [])));
        }

        return $weeks;
    }

    protected function toPlainArrays(array $items): array
    {
        return array_map(
            fn ($item) => $item instanceof Data ? $item->toArray() : $item,
            $items
        );
    }

    private function copyOverrides(ExerciseOverrides $overrides, ?string $startsAtDate = null): ExerciseOverrides
    {
        return tap(ExerciseOverrides::from($overrides->toArray()), function (ExerciseOverrides $copiedOverrides) use ($startsAtDate): void {
            $copiedOverrides->startsAtDate = $startsAtDate ?? $copiedOverrides->startsAtDate;
        });
    }
}
