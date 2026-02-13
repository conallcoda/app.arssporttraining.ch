<?php

namespace App\Training\Services;

use App\Data\Training\Config\TrainingPlanConfig;
use App\Training\Data\ExerciseOverrideData;
use App\Training\Data\TrainingBlock;
use App\Training\Data\TrainingSet;

class TrainingPlanConfigResolver
{
    public function __construct(
        private TrainingPlanConfig $config,
        private ?int $userId = null,
    ) {}

    public function forUser(?int $userId): self
    {
        return new self($this->config, $userId);
    }

    public function resolveScheduleWeeks(): array
    {
        $defaultWeeks = $this->config->defaultScheduleWeeks();

        if ($this->userId === null) {
            return $defaultWeeks;
        }

        $userOverrides = $this->config->userScheduleWeeks($this->userId);

        if (empty($userOverrides)) {
            return $defaultWeeks;
        }

        $defaultWeekIds = collect($defaultWeeks)->pluck('id')->all();

        $weeks = collect($defaultWeeks)->map(function ($week, $index) use ($userOverrides) {
            $weekId = $week['id'];
            $override = $this->findWeekOverride($userOverrides, $weekId);

            if (! isset($week['sort'])) {
                $week['sort'] = $index;
            }

            if (! $override) {
                return $week;
            }

            if (! empty($override['removed'])) {
                return null;
            }

            if (array_key_exists('linkedTo', $override)) {
                $week['linkedTo'] = $override['linkedTo'];
            }

            if (! empty($override['slots'])) {
                $week['slots'] = $this->mergeSlotOverrides($week['slots'] ?? [], $override['slots']);
            }

            return $week;
        })->filter();

        $userAddedWeeks = collect($userOverrides)
            ->filter(fn ($override) => ! in_array($override['id'] ?? null, $defaultWeekIds));

        return $weeks->merge($userAddedWeeks)
            ->sortBy('sort')
            ->values()
            ->all();
    }

    public function mergeSlotOverrides(array $baseSlots, array $overrideSlots): array
    {
        $result = $baseSlots;

        foreach ($overrideSlots as $override) {
            $day = $override['day'];
            $slot = $override['slot'];
            $programId = $override['programId'] ?? null;

            $result = array_filter($result, fn ($s) => ! ($s['day'] === $day && $s['slot'] === $slot));

            if ($programId !== null) {
                $result[] = $override;
            }
        }

        return array_values($result);
    }

    public function getResolvedSlotsForWeek(array $week, array $allWeeks): array
    {
        if ($week['linkedTo'] === null) {
            return $week['slots'] ?? [];
        }

        $sourceWeek = collect($allWeeks)->firstWhere('id', $week['linkedTo']);

        return $sourceWeek ? $this->getResolvedSlotsForWeek($sourceWeek, $allWeeks) : ($week['slots'] ?? []);
    }

    public function getResolvedSlotsDense(array $week, array $allWeeks): array
    {
        $sparse = $this->getResolvedSlotsForWeek($week, $allWeeks);

        return $this->sparseToDense($sparse);
    }

    public function sparseToDense(array $sparseSlots): array
    {
        $dense = [];
        for ($day = 0; $day < 7; $day++) {
            $dense[$day] = [
                0 => ['programId' => null],
                1 => ['programId' => null],
            ];
        }

        foreach ($sparseSlots as $slot) {
            $day = $slot['day'];
            $slotIndex = $slot['slot'];
            $dense[$day][$slotIndex] = [
                'programId' => $slot['programId'],
            ];
        }

        return $dense;
    }

    public function programIdsFromSchedule(): array
    {
        $programIds = [];
        $weeks = $this->resolveScheduleWeeks();

        foreach ($weeks as $week) {
            $slots = $this->getResolvedSlotsForWeek($week, $weeks);
            foreach ($slots as $slot) {
                $programId = $slot['programId'] ?? null;
                if ($programId !== null && ! in_array($programId, $programIds)) {
                    $programIds[] = $programId;
                }
            }
        }

        return $programIds;
    }

    public function sessionsPerWeekForProgram(int $programId): int
    {
        $weeks = $this->resolveScheduleWeeks();

        if (empty($weeks)) {
            return 2;
        }

        $firstWeek = $weeks[0] ?? null;
        if (! $firstWeek) {
            return 2;
        }

        $slots = $this->getResolvedSlotsForWeek($firstWeek, $weeks);
        $count = 0;

        foreach ($slots as $slot) {
            if (($slot['programId'] ?? null) === $programId) {
                $count++;
            }
        }

        return max(1, $count);
    }

    public function getExerciseConfig(int $exerciseId, array $pivotConfig, ?int $defaultTargetGoal = null): array
    {
        $systemTarget = $defaultTargetGoal ?? 7;
        $systemStartingReps = $pivotConfig['startingReps'] ?? ExerciseOverrideData::DEFAULT_STARTING_REPS;
        $systemSets = $pivotConfig['sets'] ?? ExerciseOverrideData::DEFAULT_SETS;
        $systemTempo = $pivotConfig['tempo'] ?? ExerciseOverrideData::DEFAULT_TEMPO;
        $systemRest = $pivotConfig['rest'] ?? ExerciseOverrideData::DEFAULT_REST;
        $oneRepMaxModifier = $pivotConfig['oneRepMaxModifier'] ?? 100;

        $defaultOverride = $this->findExerciseOverrideConfig(
            $this->config->defaultExerciseOverrides(),
            $exerciseId
        );

        $isDefaultUser = $this->userId === null;

        if ($isDefaultUser) {
            return [
                'target' => $defaultOverride['target'] ?? $systemTarget,
                'startingReps' => $defaultOverride['startingReps'] ?? $systemStartingReps,
                'sets' => $defaultOverride['sets'] ?? $systemSets,
                'tempo' => $defaultOverride['tempo'] ?? $systemTempo,
                'rest' => $defaultOverride['rest'] ?? $systemRest,
                'oneRepMaxModifier' => $oneRepMaxModifier,
                'hasTargetOverride' => isset($defaultOverride['target']),
                'hasStartingRepsOverride' => isset($defaultOverride['startingReps']),
                'hasSetsOverride' => isset($defaultOverride['sets']),
                'hasTempoOverride' => isset($defaultOverride['tempo']),
                'hasRestOverride' => isset($defaultOverride['rest']),
            ];
        }

        $userOverride = $this->findExerciseOverrideConfig(
            $this->config->userExerciseOverrides($this->userId),
            $exerciseId
        );

        return [
            'target' => $userOverride['target'] ?? $defaultOverride['target'] ?? $systemTarget,
            'startingReps' => $userOverride['startingReps'] ?? $defaultOverride['startingReps'] ?? $systemStartingReps,
            'sets' => $userOverride['sets'] ?? $defaultOverride['sets'] ?? $systemSets,
            'tempo' => $userOverride['tempo'] ?? $defaultOverride['tempo'] ?? $systemTempo,
            'rest' => $userOverride['rest'] ?? $defaultOverride['rest'] ?? $systemRest,
            'oneRepMaxModifier' => $oneRepMaxModifier,
            'hasTargetOverride' => isset($userOverride['target']),
            'hasStartingRepsOverride' => isset($userOverride['startingReps']),
            'hasSetsOverride' => isset($userOverride['sets']),
            'hasTempoOverride' => isset($userOverride['tempo']),
            'hasRestOverride' => isset($userOverride['rest']),
        ];
    }

    public function getExerciseConfigForExport(int $exerciseId, array $pivotConfig, ?int $defaultTargetGoal = null): array
    {
        $config = $this->getExerciseConfig($exerciseId, $pivotConfig, $defaultTargetGoal);

        unset(
            $config['hasTargetOverride'],
            $config['hasStartingRepsOverride'],
            $config['hasSetsOverride'],
            $config['hasTempoOverride'],
            $config['hasRestOverride'],
        );

        return $config;
    }

    public function getCellOverrides(int $exerciseId): array
    {
        $isDefaultUser = $this->userId === null;

        if ($isDefaultUser) {
            return $this->config->defaultCellOverrides($exerciseId);
        }

        $defaultOverrides = $this->config->defaultCellOverrides($exerciseId);
        $userOverrides = $this->config->userCellOverrides($this->userId, $exerciseId);

        return $this->mergeCellOverrides($defaultOverrides, $userOverrides);
    }

    public function applyCellOverrides(TrainingBlock $block, int $exerciseId): TrainingBlock
    {
        $overrides = $this->getCellOverrides($exerciseId);

        if (empty($overrides)) {
            return $block;
        }

        $weeks = $block->weeks;

        foreach ($overrides as $override) {
            $weekIndex = $override['week'];
            $sessionIndex = $override['session'];
            $setIndex = $override['set'];
            $values = $override['data'];

            if (! isset($weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex])) {
                continue;
            }

            $set = $weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex];

            $weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex] = new TrainingSet(
                reps: $values['reps'] ?? $set->reps,
                weight: $values['weight'] ?? $set->weight,
                oneRepMax: $set->oneRepMax,
            );
        }

        return $block->withWeeks($weeks);
    }

    public function applyDefaultCellOverrides(TrainingBlock $block, int $exerciseId): TrainingBlock
    {
        $overrides = $this->config->defaultCellOverrides($exerciseId);

        if (empty($overrides)) {
            return $block;
        }

        $weeks = $block->weeks;

        foreach ($overrides as $override) {
            $weekIndex = $override['week'];
            $sessionIndex = $override['session'];
            $setIndex = $override['set'];
            $values = $override['data'];

            if (! isset($weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex])) {
                continue;
            }

            $set = $weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex];

            $weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex] = new TrainingSet(
                reps: $values['reps'] ?? $set->reps,
                weight: $values['weight'] ?? $set->weight,
                oneRepMax: $set->oneRepMax,
            );
        }

        return $block->withWeeks($weeks);
    }

    public function getWeekOverrides(int $exerciseId): array
    {
        $isDefaultUser = $this->userId === null;

        if ($isDefaultUser) {
            return $this->config->defaultWeekOverrides($exerciseId);
        }

        $defaultOverrides = $this->config->defaultWeekOverrides($exerciseId);
        $userOverrides = $this->config->userWeekOverrides($this->userId, $exerciseId);

        return $this->mergeWeekOverrides($defaultOverrides, $userOverrides);
    }

    protected function mergeWeekOverrides(array $defaultOverrides, array $userOverrides): array
    {
        $merged = $defaultOverrides;

        foreach ($userOverrides as $userOverride) {
            $existingIndex = null;

            foreach ($merged as $index => $existing) {
                if ($existing['week'] === $userOverride['week']) {
                    $existingIndex = $index;

                    break;
                }
            }

            if ($existingIndex !== null) {
                $merged[$existingIndex]['data'] = array_merge($merged[$existingIndex]['data'], $userOverride['data']);
            } else {
                $merged[] = $userOverride;
            }
        }

        return $merged;
    }

    public function hasUserSchedule(int $userId): bool
    {
        $userOverrides = $this->config->userScheduleWeeks($userId);

        if (empty($userOverrides)) {
            return false;
        }

        $defaultWeekIds = collect($this->config->defaultScheduleWeeks())->pluck('id')->all();

        foreach ($userOverrides as $weekOverride) {
            $weekId = $weekOverride['id'] ?? null;

            if (! in_array($weekId, $defaultWeekIds)) {
                return true;
            }

            if (! empty($weekOverride['removed']) || ! empty($weekOverride['slots']) || array_key_exists('linkedTo', $weekOverride)) {
                return true;
            }
        }

        return false;
    }

    public function countUserScheduleChanges(int $userId): int
    {
        $userOverrides = $this->config->userScheduleWeeks($userId);

        if (empty($userOverrides)) {
            return 0;
        }

        $defaultWeekIds = collect($this->config->defaultScheduleWeeks())->pluck('id')->all();
        $count = 0;

        foreach ($userOverrides as $weekOverride) {
            $weekId = $weekOverride['id'] ?? null;

            if (! in_array($weekId, $defaultWeekIds)) {
                $count++;

                continue;
            }

            if (! empty($weekOverride['removed'])) {
                $count++;

                continue;
            }

            if (array_key_exists('linkedTo', $weekOverride)) {
                $count++;
            }

            $count += count($weekOverride['slots'] ?? []);
        }

        return $count;
    }

    protected function findWeekOverride(array $weeks, string $weekId): ?array
    {
        foreach ($weeks as $week) {
            if (($week['id'] ?? null) === $weekId) {
                return $week;
            }
        }

        return null;
    }

    protected function findExerciseOverrideConfig(array $overrides, int $exerciseId): array
    {
        foreach ($overrides as $override) {
            if ($override['id'] === $exerciseId) {
                return $override['config'] ?? [];
            }
        }

        return [];
    }

    protected function mergeCellOverrides(array $defaultOverrides, array $userOverrides): array
    {
        $merged = $defaultOverrides;

        foreach ($userOverrides as $userOverride) {
            $existingIndex = null;

            foreach ($merged as $index => $existing) {
                if ($existing['week'] === $userOverride['week']
                    && $existing['session'] === $userOverride['session']
                    && $existing['set'] === $userOverride['set']
                ) {
                    $existingIndex = $index;

                    break;
                }
            }

            if ($existingIndex !== null) {
                $merged[$existingIndex]['data'] = array_merge($merged[$existingIndex]['data'], $userOverride['data']);
            } else {
                $merged[] = $userOverride;
            }
        }

        return $merged;
    }
}
