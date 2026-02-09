<?php

namespace App\Livewire\Training\View;

use App\Cms\Livewire\Concerns\InteractsWithParentView;
use App\Data\Training\DefaultTrainingProgramData;
use App\Data\Training\UserTrainingProgramData;
use App\Models\Exercise\Exercise;
use App\Models\TrainingPlan;
use App\Models\Users\User;
use App\Support\WeekOptions;
use App\Training\Data\TrainingBlock;
use App\Training\Handlers\ExercisePlanHandlerInterface;
use App\Training\Reference\RepPercentageTable;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Plan extends Component
{
    use InteractsWithParentView;

    public TrainingPlan $trainingPlan;

    public Collection $programs;

    public Collection $users;

    #[Url(except: null, as: 'user')]
    public int|string|null $user = null;

    public function updatingUser(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public ?string $startDate = null;

    public ?int $measuredReps = null;

    public ?float $measuredWeight = null;

    public ?int $targetGoal = null;

    public array $exerciseOverrides = [];

    public array $defaultExerciseOverrides = [];

    public array $cellOverrides = [];

    public array $defaultCellOverrides = [];

    public array $weekOverrides = [];

    public array $defaultWeekOverrides = [];

    public function mount(
        TrainingPlan $trainingPlan,
        Collection $programs,
        Collection $users,
    ): void {
        $this->trainingPlan = $trainingPlan;
        $this->programs = $programs;
        $this->users = $users;
        $this->loadAthleteData();
    }

    #[On('child-changed')]
    public function handleChildChanged(string $domain): void
    {
        if ($domain === 'schedule') {
            $this->trainingPlan->refresh();
            unset($this->scheduleWeeks);
            unset($this->programIdsFromSchedule);
        }
    }

    public function userHasMeasuredData(int $userId): bool
    {
        $data = $this->trainingPlan->config->get("users.{$userId}.exerciseConfig.strength", []);

        $measuredReps = $data['measuredReps'] ?? null;
        $measuredWeight = $data['measuredWeight'] ?? null;

        return $measuredReps !== null && $measuredWeight !== null;
    }

    public function countUserOverrides(int $userId): int
    {
        $exerciseOverrides = $this->trainingPlan->config->get("users.{$userId}.exercises", []);

        $count = 0;

        foreach ($exerciseOverrides as $override) {
            $count += count($override['config'] ?? []);

            $overrides = $override['overrides'] ?? [];
            foreach ($overrides['cells'] ?? [] as $cellOverride) {
                $count += count($cellOverride['data'] ?? []);
            }
            foreach ($overrides['weeks'] ?? [] as $weekOverride) {
                $count += count($weekOverride['data'] ?? []);
            }
        }

        return $count;
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        if ($this->user === null) {
            return null;
        }

        return $this->users->firstWhere('id', $this->user);
    }

    #[Computed]
    public function scheduleWeeks(): array
    {
        $defaultWeeks = $this->trainingPlan->config->get('default.schedule.weeks', []);

        if ($this->user === null) {
            return $defaultWeeks;
        }

        $userOverrides = $this->trainingPlan->config->get("users.{$this->user}.schedule.weeks", []);

        if (empty($userOverrides)) {
            return $defaultWeeks;
        }

        $defaultWeekIds = collect($defaultWeeks)->pluck('id')->all();

        $userOverridesCollection = collect($userOverrides);

        $weeks = collect($defaultWeeks)->map(function ($week, $index) use ($userOverridesCollection) {
            $weekId = $week['id'];
            $override = $userOverridesCollection->firstWhere('id', $weekId);

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

        $userAddedWeeks = $userOverridesCollection
            ->filter(fn ($override) => ! in_array($override['id'] ?? null, $defaultWeekIds));

        return $weeks->merge($userAddedWeeks)
            ->sortBy('sort')
            ->values()
            ->all();
    }

    protected function mergeSlotOverrides(array $baseSlots, array $overrideSlots): array
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

    #[Computed]
    public function programIdsFromSchedule(): array
    {
        $programIds = [];
        $weeks = $this->scheduleWeeks;

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

    protected function getResolvedSlotsForWeek(array $week, array $allWeeks): array
    {
        if ($week['linkedTo'] === null) {
            return $week['slots'] ?? [];
        }

        $sourceWeek = collect($allWeeks)->firstWhere('id', $week['linkedTo']);

        return $sourceWeek ? $this->getResolvedSlotsForWeek($sourceWeek, $allWeeks) : ($week['slots'] ?? []);
    }

    public function getSessionsPerWeekForProgram(int $programId): int
    {
        $weeks = $this->scheduleWeeks;

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

    protected function findProgramIdForExercise(int $exerciseId): ?int
    {
        foreach ($this->programs as $program) {
            if ($program->exercises->contains('id', $exerciseId)) {
                return $program->id;
            }
        }

        return null;
    }

    #[Computed]
    public function defaultData(): DefaultTrainingProgramData
    {
        return DefaultTrainingProgramData::fromTrainingPlan($this->trainingPlan);
    }

    #[Computed]
    public function estimatedOneRepMax(): ?float
    {
        $reps = $this->measuredReps;
        $weight = $this->measuredWeight;

        if ($weight === null || $weight <= 0 || $reps === null || $reps < 1) {
            return null;
        }

        $percentage = RepPercentageTable::getPercentage($reps);

        return round($weight / $percentage, 1);
    }

    #[Computed]
    public function targetOneRepMax(): ?float
    {
        $startingMax = $this->estimatedOneRepMax;
        $goal = $this->targetGoal ?? $this->defaultData->targetGoal;

        if ($startingMax === null || $goal === null) {
            return null;
        }

        return round($startingMax * (1 + $goal / 100), 1);
    }

    public function getPivotConfig(int $programId, int $exerciseId): array
    {
        $pivot = \App\Models\TrainingPlanProgramExercise::query()
            ->where('training_plan_program_id', $programId)
            ->where('exercise_id', $exerciseId)
            ->first();

        if (! $pivot) {
            return [];
        }

        $configData = $pivot->config;

        if ($configData instanceof \Spatie\SchemalessAttributes\SchemalessAttributes) {
            return $configData->all();
        }

        if (is_array($configData)) {
            return $configData;
        }

        return [];
    }

    protected function findPivotConfigForExercise(int $exerciseId): array
    {
        $pivot = \App\Models\TrainingPlanProgramExercise::query()
            ->whereHas('program', function ($query) {
                $query->where('training_plan_id', $this->trainingPlan->id);
            })
            ->where('exercise_id', $exerciseId)
            ->first();

        if (! $pivot) {
            return [];
        }

        $configData = $pivot->config;

        if ($configData instanceof \Spatie\SchemalessAttributes\SchemalessAttributes) {
            return $configData->all();
        }

        if (is_array($configData)) {
            return $configData;
        }

        return [];
    }

    protected function getEffectiveCellValue(int $exerciseId, int $weekIndex, int $sessionIndex, int $setIndex, string $field): mixed
    {
        $exercise = Exercise::find($exerciseId);
        if (! $exercise) {
            return null;
        }

        $pivotConfig = $this->findPivotConfigForExercise($exerciseId);

        $programId = $this->findProgramIdForExercise($exerciseId);
        if ($programId === null) {
            return null;
        }

        $block = $this->generateBlock($exercise, $pivotConfig, $programId);

        if (! $block) {
            return null;
        }

        $isDefaultUser = $this->user === null;

        if (! $isDefaultUser) {
            $block = $this->applyDefaultCellOverrides($block, $exerciseId);
        }

        $weeks = $block->weeks;

        if (! isset($weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex])) {
            return null;
        }

        $set = $weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex];

        return $set->{$field} ?? null;
    }

    protected function applyDefaultCellOverrides(TrainingBlock $block, int $exerciseId): TrainingBlock
    {
        $overrides = $this->defaultCellOverrides[$exerciseId] ?? [];

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

            $weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex] = $weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex]->withOverrides($values);
        }

        return $block->withWeeks($weeks);
    }

    #[Computed]
    public function weeks(): int
    {
        return count($this->scheduleWeeks);
    }

    #[Computed]
    public function weekOptions(): array
    {
        return WeekOptions::generate();
    }

    public function selectUser(?int $userId): void
    {
        $this->user = $userId;
        $this->loadAthleteData();
        $this->dispatch('plan-user-changed', userId: $userId);
    }

    public function loadAthleteData(): void
    {
        if ($this->user === null) {
            $data = $this->defaultData;
            $this->startDate = $data->startDate;
            $this->measuredReps = $data->measuredReps;
            $this->measuredWeight = $data->measuredWeight;
            $this->targetGoal = $data->targetGoal;
        } else {
            $userData = UserTrainingProgramData::fromTrainingPlan($this->trainingPlan, $this->user);
            $this->startDate = $userData->startDate ?? $this->defaultData->startDate;
            $this->measuredReps = $userData->measuredReps;
            $this->measuredWeight = $userData->measuredWeight;
            $this->targetGoal = $userData->targetGoal ?? $this->defaultData->targetGoal;
        }

        unset($this->scheduleWeeks);
        unset($this->programIdsFromSchedule);
        $this->loadExerciseOverrides();
    }

    protected function loadExerciseOverrides(): void
    {
        $this->exerciseOverrides = [];
        $this->defaultExerciseOverrides = [];
        $this->cellOverrides = [];
        $this->defaultCellOverrides = [];
        $this->weekOverrides = [];
        $this->defaultWeekOverrides = [];

        $this->defaultExerciseOverrides = $this->trainingPlan->config->get('default.exercises', []);

        foreach ($this->defaultExerciseOverrides as $exercise) {
            $exerciseId = $exercise['id'];
            $overrides = $exercise['overrides'] ?? [];
            if (! empty($overrides['cells'])) {
                $this->defaultCellOverrides[$exerciseId] = $overrides['cells'];
            }
            if (! empty($overrides['weeks'])) {
                $this->defaultWeekOverrides[$exerciseId] = $overrides['weeks'];
            }
        }

        if ($this->user !== null) {
            $this->exerciseOverrides = $this->trainingPlan->config->get("users.{$this->user}.exercises", []);

            foreach ($this->exerciseOverrides as $exercise) {
                $exerciseId = $exercise['id'];
                $overrides = $exercise['overrides'] ?? [];
                if (! empty($overrides['cells'])) {
                    $this->cellOverrides[$exerciseId] = $overrides['cells'];
                }
                if (! empty($overrides['weeks'])) {
                    $this->weekOverrides[$exerciseId] = $overrides['weeks'];
                }
            }
        }
    }

    public function updated(string $property): void
    {
        $trackedProperties = ['startDate', 'measuredReps', 'measuredWeight', 'targetGoal'];
        $baseProperty = explode('.', $property)[0];

        if (! in_array($baseProperty, $trackedProperties)) {
            return;
        }

        if ($this->user === null) {
            $data = new DefaultTrainingProgramData(
                startDate: $this->startDate,
                measuredReps: $this->measuredReps,
                measuredWeight: $this->measuredWeight,
                targetGoal: $this->targetGoal,
            );
            $data->persist($this->trainingPlan);
        } else {
            $data = new UserTrainingProgramData(
                userId: $this->user,
                startDate: $this->startDate,
                measuredReps: $this->measuredReps,
                measuredWeight: $this->measuredWeight,
                targetGoal: $this->targetGoal,
            );
            $data->persist($this->trainingPlan, $this->defaultData);
        }

        $this->trainingPlan->refresh();
    }

    public function updateExerciseOverride(int $exerciseId, string $field, mixed $value): void
    {
        $isDefaultUser = $this->user === null;
        $property = $isDefaultUser ? 'defaultExerciseOverrides' : 'exerciseOverrides';

        $index = $this->findExerciseOverrideIndex($this->{$property}, $exerciseId);

        if ($index !== null) {
            $this->{$property}[$index]['config'][$field] = $value;
        } else {
            $this->{$property}[] = [
                'id' => $exerciseId,
                'config' => [$field => $value],
            ];
        }

        $this->persistExerciseOverrides();
    }

    protected function persistExerciseOverrides(): void
    {
        $isDefaultUser = $this->user === null;
        $extraKey = $isDefaultUser ? 'default.exercises' : "users.{$this->user}.exercises";

        $overrides = $isDefaultUser
            ? $this->defaultExerciseOverrides
            : $this->exerciseOverrides;

        $overrides = $this->rebuildExercisesWithOverrides($isDefaultUser, $overrides);

        if (empty($overrides)) {
            $this->trainingPlan->config->forget($extraKey);
        } else {
            $this->trainingPlan->config->set($extraKey, array_values($overrides));
        }

        $this->trainingPlan->save();
    }

    protected function persistExerciseOverrideData(int $exerciseId, string $key, array $data): void
    {
        $isDefaultUser = $this->user === null;
        $property = $isDefaultUser ? 'defaultExerciseOverrides' : 'exerciseOverrides';
        $extraKey = $isDefaultUser ? 'default.exercises' : "users.{$this->user}.exercises";

        $index = $this->findExerciseOverrideIndex($this->{$property}, $exerciseId);

        if ($index === null && ! empty($data)) {
            $this->{$property}[] = [
                'id' => $exerciseId,
                'config' => [],
                'overrides' => [$key => $data],
            ];
        } elseif ($index !== null) {
            if (empty($data)) {
                unset($this->{$property}[$index]['overrides'][$key]);
                if (empty($this->{$property}[$index]['overrides'])) {
                    unset($this->{$property}[$index]['overrides']);
                }
            } else {
                $this->{$property}[$index]['overrides'][$key] = $data;
            }
        }

        $this->cleanEmptyExerciseOverrides($property);

        $overrides = $this->{$property};

        if (empty($overrides)) {
            $this->trainingPlan->config->forget($extraKey);
        } else {
            $this->trainingPlan->config->set($extraKey, array_values($overrides));
        }

        $this->trainingPlan->save();
    }

    protected function rebuildExercisesWithOverrides(bool $isDefaultUser, array $exercises): array
    {
        $cellOverrides = $isDefaultUser ? $this->defaultCellOverrides : $this->cellOverrides;
        $weekOverrides = $isDefaultUser ? $this->defaultWeekOverrides : $this->weekOverrides;

        foreach ($exercises as &$exercise) {
            $id = $exercise['id'];
            $overrides = $exercise['overrides'] ?? [];

            if (! empty($cellOverrides[$id])) {
                $overrides['cells'] = $cellOverrides[$id];
            } else {
                unset($overrides['cells']);
            }

            if (! empty($weekOverrides[$id])) {
                $overrides['weeks'] = $weekOverrides[$id];
            } else {
                unset($overrides['weeks']);
            }

            if (! empty($overrides)) {
                $exercise['overrides'] = $overrides;
            } else {
                unset($exercise['overrides']);
            }
        }

        return $exercises;
    }

    protected function cleanEmptyExerciseOverrides(string $property): void
    {
        $this->{$property} = array_values(array_filter($this->{$property}, function ($exercise) {
            $hasConfig = ! empty($exercise['config']);
            $hasOverrides = ! empty($exercise['overrides']);

            return $hasConfig || $hasOverrides;
        }));
    }

    public function getExerciseConfig(Exercise $exercise, array $pivotConfig): array
    {
        $handler = $exercise->type->getPlanHandler();
        $defaultOverride = $this->getExerciseOverrideConfig($this->defaultExerciseOverrides, $exercise->id);
        $userOverride = $this->getExerciseOverrideConfig($this->exerciseOverrides, $exercise->id);
        $isDefaultUser = $this->user === null;

        return $handler->resolveConfig($exercise, $pivotConfig, $defaultOverride, $userOverride, $isDefaultUser);
    }

    public function getHandlerForExercise(Exercise $exercise): ExercisePlanHandlerInterface
    {
        return $exercise->type->getPlanHandler();
    }

    public function generateBlock(Exercise $exercise, array $pivotConfig, int $programId): ?TrainingBlock
    {
        $handler = $exercise->type->getPlanHandler();
        $config = $this->getExerciseConfig($exercise, $pivotConfig);
        $sessionsPerWeek = $this->getSessionsPerWeekForProgram($programId);

        $measuredData = $handler->needsMeasuredData()
            ? ['measuredWeight' => $this->measuredWeight, 'measuredReps' => $this->measuredReps]
            : null;

        return $handler->generateBlock($config, $this->weeks, $sessionsPerWeek, $measuredData);
    }

    public function getPlaceholder(string $field): mixed
    {
        if ($this->user === null) {
            return null;
        }

        if (! in_array($field, ['startDate', 'targetGoal'])) {
            return null;
        }

        return $this->defaultData->{$field};
    }

    public function getStartDateLabel(?string $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return $this->weekOptions[$date] ?? $date;
    }

    public function updateCellOverride(int $exerciseId, int $weekIndex, int $setIndex, string $field, mixed $value): void
    {
        $isDefaultUser = $this->user === null;
        $programId = $this->findProgramIdForExercise($exerciseId);
        $sessionsPerWeek = $programId ? $this->getSessionsPerWeekForProgram($programId) : 1;

        for ($s = 0; $s < $sessionsPerWeek; $s++) {
            $effectiveValue = $this->getEffectiveCellValue($exerciseId, $weekIndex, $s, $setIndex, $field);
            $valuesMatch = $this->cellValuesMatch($value, $effectiveValue);

            $this->applyCellOverrideUpdate($isDefaultUser, $exerciseId, $weekIndex, $s, $setIndex, $field, $value, $valuesMatch);
        }

        $this->persistCellOverrides($exerciseId);
    }

    protected function applyCellOverrideUpdate(bool $isDefaultUser, int $exerciseId, int $weekIndex, int $session, int $setIndex, string $field, mixed $value, bool $valuesMatch): void
    {
        $property = $isDefaultUser ? 'defaultCellOverrides' : 'cellOverrides';

        if (! isset($this->{$property}[$exerciseId])) {
            $this->{$property}[$exerciseId] = [];
        }

        $index = $this->findCellOverrideIndex($this->{$property}[$exerciseId], $weekIndex, $session, $setIndex);

        if ($valuesMatch) {
            if ($index !== null) {
                unset($this->{$property}[$exerciseId][$index]['data'][$field]);
                if (empty($this->{$property}[$exerciseId][$index]['data'])) {
                    array_splice($this->{$property}[$exerciseId], $index, 1);
                }
                if (empty($this->{$property}[$exerciseId])) {
                    unset($this->{$property}[$exerciseId]);
                }
            }
        } else {
            if ($index !== null) {
                $this->{$property}[$exerciseId][$index]['data'][$field] = $value;
            } else {
                $this->{$property}[$exerciseId][] = [
                    'week' => $weekIndex,
                    'session' => $session,
                    'set' => $setIndex,
                    'data' => [$field => $value],
                ];
            }
        }
    }

    protected function findExerciseOverrideIndex(array $overrides, int $exerciseId): ?int
    {
        foreach ($overrides as $index => $override) {
            if ($override['id'] === $exerciseId) {
                return $index;
            }
        }

        return null;
    }

    protected function getExerciseOverrideConfig(array $overrides, int $exerciseId): array
    {
        $index = $this->findExerciseOverrideIndex($overrides, $exerciseId);

        return $index !== null ? ($overrides[$index]['config'] ?? []) : [];
    }

    protected function findCellOverrideIndex(array $overrides, int $week, int $session, int $set): ?int
    {
        foreach ($overrides as $index => $override) {
            if ($override['week'] === $week && $override['session'] === $session && $override['set'] === $set) {
                return $index;
            }
        }

        return null;
    }

    protected function findWeekOverrideIndex(array $overrides, int $weekIndex): ?int
    {
        foreach ($overrides as $index => $override) {
            if ($override['week'] === $weekIndex) {
                return $index;
            }
        }

        return null;
    }

    protected function findWeekOverrideData(array $overrides, int $weekIndex): array
    {
        $index = $this->findWeekOverrideIndex($overrides, $weekIndex);

        return $index !== null ? ($overrides[$index]['data'] ?? []) : [];
    }

    protected function mergeWeekOverrides(array $defaultOverrides, array $userOverrides): array
    {
        $merged = $defaultOverrides;

        foreach ($userOverrides as $userOverride) {
            $existingIndex = $this->findWeekOverrideIndex($merged, $userOverride['week']);

            if ($existingIndex !== null) {
                $merged[$existingIndex]['data'] = array_merge($merged[$existingIndex]['data'], $userOverride['data']);
            } else {
                $merged[] = $userOverride;
            }
        }

        return $merged;
    }

    protected function cellValuesMatch(mixed $value, mixed $originalValue): bool
    {
        if ($originalValue === null) {
            return false;
        }

        return abs((float) $value - (float) $originalValue) < 0.001;
    }

    protected function persistCellOverrides(int $exerciseId): void
    {
        $isDefaultUser = $this->user === null;

        $overrides = $isDefaultUser
            ? ($this->defaultCellOverrides[$exerciseId] ?? [])
            : ($this->cellOverrides[$exerciseId] ?? []);

        $this->persistExerciseOverrideData($exerciseId, 'cells', $overrides);
    }

    public function getCellOverrides(int $exerciseId): array
    {
        $isDefaultUser = $this->user === null;

        if ($isDefaultUser) {
            return $this->defaultCellOverrides[$exerciseId] ?? [];
        }

        $defaultOverrides = $this->defaultCellOverrides[$exerciseId] ?? [];
        $userOverrides = $this->cellOverrides[$exerciseId] ?? [];

        $merged = $defaultOverrides;

        foreach ($userOverrides as $userOverride) {
            $existingIndex = $this->findCellOverrideIndex($merged, $userOverride['week'], $userOverride['session'], $userOverride['set']);

            if ($existingIndex !== null) {
                $merged[$existingIndex]['data'] = array_merge($merged[$existingIndex]['data'], $userOverride['data']);
            } else {
                $merged[] = $userOverride;
            }
        }

        return $merged;
    }

    public function getUserSpecificCellOverrides(int $exerciseId): array
    {
        $isDefaultUser = $this->user === null;

        if ($isDefaultUser) {
            return $this->defaultCellOverrides[$exerciseId] ?? [];
        }

        return $this->cellOverrides[$exerciseId] ?? [];
    }

    public function updateWeekOverride(int $exerciseId, int $weekIndex, string $field, mixed $value): void
    {
        $isDefaultUser = $this->user === null;
        $property = $isDefaultUser ? 'defaultWeekOverrides' : 'weekOverrides';

        $effectiveValue = $this->getEffectiveWeekValue($exerciseId, $weekIndex, $field);
        $valuesMatch = $this->weekValuesMatch($value, $effectiveValue, $field);

        if (! isset($this->{$property}[$exerciseId])) {
            $this->{$property}[$exerciseId] = [];
        }

        $index = $this->findWeekOverrideIndex($this->{$property}[$exerciseId], $weekIndex);

        if ($valuesMatch) {
            if ($index !== null) {
                unset($this->{$property}[$exerciseId][$index]['data'][$field]);
                if (empty($this->{$property}[$exerciseId][$index]['data'])) {
                    array_splice($this->{$property}[$exerciseId], $index, 1);
                }
                if (empty($this->{$property}[$exerciseId])) {
                    unset($this->{$property}[$exerciseId]);
                }
            }
        } else {
            if ($index !== null) {
                $this->{$property}[$exerciseId][$index]['data'][$field] = $value;
            } else {
                $this->{$property}[$exerciseId][] = [
                    'week' => $weekIndex,
                    'data' => [$field => $value],
                ];
            }
        }

        $this->persistWeekOverrides($exerciseId);
    }

    protected function getEffectiveWeekValue(int $exerciseId, int $weekIndex, string $field): mixed
    {
        $exercise = Exercise::find($exerciseId);
        if (! $exercise) {
            return null;
        }

        $pivotConfig = $this->findPivotConfigForExercise($exerciseId);
        $handler = $exercise->type->getPlanHandler();
        $config = $this->getExerciseConfig($exercise, $pivotConfig);
        $defaultWeekValues = $handler->getDefaultWeekValues($config);

        $defaultExerciseOverride = $this->getExerciseOverrideConfig($this->defaultExerciseOverrides, $exerciseId);
        $defaultWeekOverride = $this->findWeekOverrideData($this->defaultWeekOverrides[$exerciseId] ?? [], $weekIndex);

        return $defaultWeekOverride[$field] ?? $defaultExerciseOverride[$field] ?? $defaultWeekValues[$field] ?? null;
    }

    protected function weekValuesMatch(mixed $value, mixed $originalValue, string $field): bool
    {
        if ($originalValue === null) {
            return false;
        }

        if ($field === 'tut') {
            return (string) $value === (string) $originalValue;
        }

        return (int) $value === (int) $originalValue;
    }

    protected function persistWeekOverrides(int $exerciseId): void
    {
        $isDefaultUser = $this->user === null;

        $overrides = $isDefaultUser
            ? ($this->defaultWeekOverrides[$exerciseId] ?? [])
            : ($this->weekOverrides[$exerciseId] ?? []);

        $this->persistExerciseOverrideData($exerciseId, 'weeks', $overrides);
    }

    public function getWeekOverrides(int $exerciseId): array
    {
        $isDefaultUser = $this->user === null;

        if ($isDefaultUser) {
            return $this->defaultWeekOverrides[$exerciseId] ?? [];
        }

        $defaultOverrides = $this->defaultWeekOverrides[$exerciseId] ?? [];
        $userOverrides = $this->weekOverrides[$exerciseId] ?? [];

        return $this->mergeWeekOverrides($defaultOverrides, $userOverrides);
    }

    public function getUserSpecificWeekOverrides(int $exerciseId): array
    {
        $isDefaultUser = $this->user === null;

        if ($isDefaultUser) {
            return $this->defaultWeekOverrides[$exerciseId] ?? [];
        }

        return $this->weekOverrides[$exerciseId] ?? [];
    }

    public function resetExerciseOverrides(int $exerciseId): void
    {
        $isDefaultUser = $this->user === null;
        $property = $isDefaultUser ? 'defaultExerciseOverrides' : 'exerciseOverrides';

        $exerciseIndex = $this->findExerciseOverrideIndex($this->{$property}, $exerciseId);
        if ($exerciseIndex !== null) {
            array_splice($this->{$property}, $exerciseIndex, 1);
        }

        if ($isDefaultUser) {
            unset($this->defaultCellOverrides[$exerciseId]);
            unset($this->defaultWeekOverrides[$exerciseId]);
        } else {
            unset($this->cellOverrides[$exerciseId]);
            unset($this->weekOverrides[$exerciseId]);
        }

        $this->persistExerciseOverrides();
    }

    public function getWeekValue(int $exerciseId, int $weekIndex, string $field, array $config): mixed
    {
        $weekOverrides = $this->getWeekOverrides($exerciseId);
        $weekData = $this->findWeekOverrideData($weekOverrides, $weekIndex);

        if (isset($weekData[$field])) {
            return $weekData[$field];
        }

        return $config[$field] ?? null;
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

            $weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex] = $weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex]->withOverrides($values);
        }

        return $block->withWeeks($weeks);
    }

    public function render()
    {
        return view('livewire.training.view.plan');
    }
}
