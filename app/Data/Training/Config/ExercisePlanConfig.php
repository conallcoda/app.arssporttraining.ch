<?php

namespace App\Data\Training\Config;

use App\Data\Exercise\ExerciseConfig;
use App\Data\Training\Config\Target\TargetConfig;
use App\Support\Training\GridOverrideNormalizer;
use Coda\Cms\Data\AbstractConfig;
use Spatie\LaravelData\Optional;

class ExercisePlanConfig extends AbstractConfig
{
    private bool $overrideValuesHydrated = false;

    public function __construct(
        public array|Optional $schedule,
        public TargetConfig|Optional $target,
        /** @var array<int, ExerciseOverrides> */
        public array $exercises = [],
        /** @var array<int, array<int, ExerciseOverrides>> */
        public array $userExercises = [],
        public int $weeks = 5,
        /** @var array<int, array<string, mixed>> */
        public array $overrideValues = [],
    ) {}

    public static function initialize(): self
    {
        return self::from([
            'target' => [
                'measuredReps' => 1,
                'measuredWeight' => 50,
                'targetGoal' => 10,
            ],
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
        $this->hydrateOverrideValues();

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
        $this->hydrateOverrideValues();
        $this->exercises[$programExerciseId] = $overrides;
    }

    public function removeExerciseOverrides(int $programExerciseId): void
    {
        $this->hydrateOverrideValues();
        unset($this->exercises[$programExerciseId]);
    }

    public function userExerciseOverrides(int $userId, int $programExerciseId): ExerciseOverrides
    {
        $this->hydrateOverrideValues();

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
        $this->hydrateOverrideValues();

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
        $this->hydrateOverrideValues();

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
        $this->hydrateOverrideValues();
        unset($this->userExercises[$userId][$programExerciseId]);

        if (($this->userExercises[$userId] ?? []) === []) {
            unset($this->userExercises[$userId]);
        }
    }

    public function removeExerciseOverridesForAllUsers(int $programExerciseId): void
    {
        $this->hydrateOverrideValues();

        foreach (array_keys($this->userExercises) as $userId) {
            $this->removeUserExerciseOverrides((int) $userId, $programExerciseId);
        }
    }

    /** @return array<int, array<int, ExerciseOverrides>> */
    public function allUserExerciseOverrides(): array
    {
        $this->hydrateOverrideValues();

        foreach (array_keys($this->userExercises) as $userId) {
            foreach (array_keys($this->userExercises[$userId] ?? []) as $programExerciseId) {
                $this->userExerciseOverrides((int) $userId, (int) $programExerciseId);
            }
        }

        return $this->userExercises;
    }

    public function remapUserExerciseOverrides(array $pivotIdMap): void
    {
        $this->hydrateOverrideValues();
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
        $this->hydrateOverrideValues();
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
        $this->hydrateOverrideValues();
        $sourceConfig->hydrateOverrideValues();

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
        $this->hydrateOverrideValues();
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
        $this->hydrateOverrideValues();

        return ! empty($this->exercises);
    }

    public function resetDefaults(): void
    {
        $this->hydrateOverrideValues();
        $this->exercises = [];
    }

    public function resetAll(): void
    {
        $this->resetDefaults();
    }

    /** @return array<string, mixed> */
    public function toPersistedArray(): array
    {
        $this->hydrateOverrideValues();

        $data = $this->toArray();
        $data['overrideValues'] = $this->flattenOverrideValues();
        $data['exercises'] = $this->stripPersistedOverrideMaps($data['exercises'] ?? []);
        $data['userExercises'] = array_map(
            fn (array $overridesByUser): array => $this->stripPersistedOverrideMaps($overridesByUser),
            $data['userExercises'] ?? [],
        );

        return $data;
    }

    private function copyOverrides(ExerciseOverrides $overrides, ?string $startsAtDate = null): ExerciseOverrides
    {
        return tap(ExerciseOverrides::from($overrides->toArray()), function (ExerciseOverrides $copiedOverrides) use ($startsAtDate): void {
            $copiedOverrides->startsAtDate = $startsAtDate ?? $copiedOverrides->startsAtDate;
        });
    }

    private function hydrateOverrideValues(): void
    {
        if ($this->overrideValuesHydrated) {
            return;
        }

        $this->overrideValuesHydrated = true;

        foreach ($this->overrideValues as $row) {
            $programExerciseId = (int) ($row['programExerciseId'] ?? 0);

            if ($programExerciseId <= 0) {
                continue;
            }

            $userId = array_key_exists('userId', $row) && $row['userId'] !== null
                ? (int) $row['userId']
                : null;
            $scope = (string) ($row['scope'] ?? 'current');
            $target = (string) ($row['target'] ?? 'cell');
            $week = (int) ($row['week'] ?? 0);
            $session = (int) ($row['session'] ?? 0);
            $settingKey = (string) ($row['settingKey'] ?? '');

            if ($settingKey === '') {
                continue;
            }

            $overrides = $this->resolveOverrideBucket($programExerciseId, $userId);
            $gridKey = match ($scope) {
                'historical' => 'historicalGridOverrides',
                'baseline' => 'baselineGridOverrides',
                default => 'gridOverrides',
            };

            $existingGridOverrides = $overrides->{$gridKey} ?? ['sessions' => [], 'cells' => []];
            $newEntry = $target === 'session'
                ? ['sessions' => [[
                    'week' => $week,
                    'session' => $session,
                    'data' => [$settingKey => $row['value'] ?? null],
                ]], 'cells' => []]
                : ['sessions' => [], 'cells' => [[
                    'week' => $week,
                    'session' => $session,
                    'set' => (int) ($row['set'] ?? 0),
                    'data' => [$settingKey => $row['value'] ?? null],
                ]]];

            $overrides->{$gridKey} = EffectiveExerciseConfig::mergeGridOverrides($existingGridOverrides, $newEntry);
        }

    }

    private function resolveOverrideBucket(int $programExerciseId, ?int $userId): ExerciseOverrides
    {
        if ($userId === null) {
            $current = $this->exercises[$programExerciseId] ?? null;

            if (! $current instanceof ExerciseOverrides) {
                $current = $current === null ? new ExerciseOverrides : ExerciseOverrides::from($current);
                $this->exercises[$programExerciseId] = $current;
            }

            return $current;
        }

        $current = $this->userExercises[$userId][$programExerciseId] ?? null;

        if (! $current instanceof ExerciseOverrides) {
            $current = $current === null ? new ExerciseOverrides : ExerciseOverrides::from($current);
            $this->userExercises[$userId][$programExerciseId] = $current;
        }

        return $current;
    }

    /** @return array<int, array<string, mixed>> */
    private function flattenOverrideValues(): array
    {
        $rows = [];

        foreach (array_keys($this->exercises) as $programExerciseId) {
            $overrides = $this->defaultExerciseOverrides((int) $programExerciseId);
            array_push($rows, ...$this->flattenOverrideRows((int) $programExerciseId, null, $overrides));
        }

        foreach ($this->allUserExerciseOverrides() as $userId => $overridesByExercise) {
            foreach ($overridesByExercise as $programExerciseId => $overrides) {
                array_push($rows, ...$this->flattenOverrideRows((int) $programExerciseId, (int) $userId, $overrides));
            }
        }

        usort($rows, static function (array $a, array $b): int {
            return [
                $a['programExerciseId'],
                $a['userId'] ?? -1,
                $a['scope'],
                $a['target'],
                $a['week'],
                $a['session'],
                $a['set'] ?? -1,
                $a['settingKey'],
            ] <=> [
                $b['programExerciseId'],
                $b['userId'] ?? -1,
                $b['scope'],
                $b['target'],
                $b['week'],
                $b['session'],
                $b['set'] ?? -1,
                $b['settingKey'],
            ];
        });

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function flattenOverrideRows(int $programExerciseId, ?int $userId, ExerciseOverrides $overrides): array
    {
        $current = GridOverrideNormalizer::normalize($overrides->gridOverrides);
        $historical = GridOverrideNormalizer::normalize($overrides->historicalGridOverrides);
        $baseline = GridOverrideNormalizer::normalize($overrides->baselineGridOverrides ?? ['sessions' => [], 'cells' => []]);

        return array_merge(
            $this->flattenGridOverrideRows($programExerciseId, $userId, 'current', $current),
            $this->flattenGridOverrideRows($programExerciseId, $userId, 'historical', $historical),
            $this->flattenGridOverrideRows($programExerciseId, $userId, 'baseline', $baseline),
        );
    }

    /**
     * @param  array{sessions?: array<int, array<string, mixed>>, cells?: array<int, array<string, mixed>>}  $gridOverrides
     * @return array<int, array<string, mixed>>
     */
    private function flattenGridOverrideRows(int $programExerciseId, ?int $userId, string $scope, array $gridOverrides): array
    {
        $rows = [];

        foreach ($gridOverrides['sessions'] ?? [] as $override) {
            foreach (($override['data'] ?? []) as $settingKey => $value) {
                $rows[] = [
                    'programExerciseId' => $programExerciseId,
                    'userId' => $userId,
                    'scope' => $scope,
                    'target' => 'session',
                    'week' => (int) ($override['week'] ?? 0),
                    'session' => (int) ($override['session'] ?? 0),
                    'settingKey' => (string) $settingKey,
                    'value' => $value,
                ];
            }
        }

        foreach ($gridOverrides['cells'] ?? [] as $override) {
            foreach (($override['data'] ?? []) as $settingKey => $value) {
                $rows[] = [
                    'programExerciseId' => $programExerciseId,
                    'userId' => $userId,
                    'scope' => $scope,
                    'target' => 'cell',
                    'week' => (int) ($override['week'] ?? 0),
                    'session' => (int) ($override['session'] ?? 0),
                    'set' => (int) ($override['set'] ?? 0),
                    'settingKey' => (string) $settingKey,
                    'value' => $value,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<int|string, mixed>  $overridesByExercise
     * @return array<int|string, mixed>
     */
    private function stripPersistedOverrideMaps(array $overridesByExercise): array
    {
        $cleaned = [];

        foreach ($overridesByExercise as $programExerciseId => $overrides) {
            $row = $overrides instanceof ExerciseOverrides ? $overrides->toArray() : $overrides;
            unset($row['gridOverrides'], $row['historicalGridOverrides'], $row['baselineGridOverrides']);

            $hasNonEmptyValue = collect($row)->contains(function (mixed $value): bool {
                if (is_array($value)) {
                    return $value !== [];
                }

                return $value !== null;
            });

            if ($hasNonEmptyValue) {
                $cleaned[$programExerciseId] = $row;
            }
        }

        return $cleaned;
    }
}
