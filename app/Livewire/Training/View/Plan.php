<?php

namespace App\Livewire\Training\View;

use App\Cms\Livewire\Concerns\InteractsWithParentView;
use App\Data\Training\DefaultTrainingProgramData;
use App\Data\Training\UserTrainingProgramData;
use App\Models\Exercise\Exercise;
use App\Models\TrainingPlan;
use App\Models\Users\User;
use App\Support\WeekOptions;
use App\Training\Data\ExerciseOverrideData;
use App\Training\Data\TrainingBlock;
use App\Training\Reference\RepPercentageTable;
use App\Training\Services\TrainingBlockGenerator;
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
        $data = $this->trainingPlan->config->get("users.{$userId}.training_plan", []);

        $measuredReps = $data['measuredReps'] ?? null;
        $measuredWeight = $data['measuredWeight'] ?? null;

        return $measuredReps !== null && $measuredWeight !== null;
    }

    public function countUserOverrides(int $userId): int
    {
        $exerciseOverrides = $this->trainingPlan->config->get("users.{$userId}.exercises", []);
        $cellOverrides = $this->trainingPlan->config->get("users.{$userId}.cells", []);
        $weekOverrides = $this->trainingPlan->config->get("users.{$userId}.weeks", []);

        $count = 0;

        foreach ($exerciseOverrides as $overrides) {
            $count += count($overrides);
        }

        foreach ($cellOverrides as $exerciseCells) {
            foreach ($exerciseCells as $cellValues) {
                $count += count($cellValues);
            }
        }

        foreach ($weekOverrides as $exerciseWeeks) {
            foreach ($exerciseWeeks as $weekValues) {
                $count += count($weekValues);
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
        $defaultWeeks = $this->trainingPlan->config->get('schedule.weeks', []);

        if ($this->user === null) {
            return $defaultWeeks;
        }

        $userOverrides = $this->trainingPlan->config->get("users.{$this->user}.schedule.weeks", []);

        if (empty($userOverrides)) {
            return $defaultWeeks;
        }

        $defaultWeekIds = collect($defaultWeeks)->pluck('id')->all();

        $weeks = collect($defaultWeeks)->map(function ($week, $index) use ($userOverrides) {
            $weekId = $week['id'];
            $override = $userOverrides[$weekId] ?? null;

            if (! isset($week['sort'])) {
                $week['sort'] = $index;
            }

            if (! $override) {
                return $week;
            }

            if (! empty($override['removed'])) {
                return null;
            }

            if (array_key_exists('linkedToWeekId', $override)) {
                $week['linkedToWeekId'] = $override['linkedToWeekId'];
            }

            if (! empty($override['slots'])) {
                $week['slots'] = $this->mergeSlotOverrides($week['slots'] ?? [], $override['slots']);
            }

            return $week;
        })->filter();

        $userAddedWeeks = collect($userOverrides)
            ->filter(fn ($override, $weekId) => ! in_array($weekId, $defaultWeekIds));

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
        if ($week['linkedToWeekId'] === null) {
            return $week['slots'] ?? [];
        }

        $sourceWeek = collect($allWeeks)->firstWhere('id', $week['linkedToWeekId']);

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
        $pivotConfigData = $this->findPivotConfigForExercise($exerciseId);
        $pivotConfig = [
            'oneRepMaxModifier' => $pivotConfigData['oneRepMaxModifier'] ?? 100,
            'startingReps' => $pivotConfigData['startingReps'] ?? null,
            'sets' => $pivotConfigData['sets'] ?? null,
        ];

        $programId = $this->findProgramIdForExercise($exerciseId);
        if ($programId === null) {
            return null;
        }

        $block = $this->generateBlock($exerciseId, $pivotConfig, $programId);

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

        foreach ($overrides as $cellKey => $values) {
            if (! preg_match('/^w(\d+)-s(\d+)-set(\d+)$/', $cellKey, $matches)) {
                continue;
            }

            $weekIndex = (int) $matches[1];
            $sessionIndex = (int) $matches[2];
            $setIndex = (int) $matches[3];

            if (! isset($weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex])) {
                continue;
            }

            $set = $weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex];

            $newReps = $values['reps'] ?? $set->reps;
            $newWeight = $values['weight'] ?? $set->weight;

            $weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex] = new \App\Training\Data\TrainingSet(
                reps: $newReps,
                weight: $newWeight,
                oneRepMax: $set->oneRepMax,
            );
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

        $defaultExercisesData = $this->trainingPlan->config->get('users.default.exercises', []);
        foreach ($defaultExercisesData as $exerciseId => $overrideData) {
            $this->defaultExerciseOverrides[$exerciseId] = $overrideData;
        }

        $defaultCellData = $this->trainingPlan->config->get('users.default.cells', []);
        foreach ($defaultCellData as $exerciseId => $cells) {
            $this->defaultCellOverrides[$exerciseId] = $cells;
        }

        $defaultWeekData = $this->trainingPlan->config->get('users.default.weeks', []);
        foreach ($defaultWeekData as $exerciseId => $weeks) {
            $this->defaultWeekOverrides[$exerciseId] = $weeks;
        }

        if ($this->user !== null) {
            $exercisesData = $this->trainingPlan->config->get("users.{$this->user}.exercises", []);

            foreach ($exercisesData as $exerciseId => $overrideData) {
                $this->exerciseOverrides[$exerciseId] = $overrideData;
            }

            $cellData = $this->trainingPlan->config->get("users.{$this->user}.cells", []);
            foreach ($cellData as $exerciseId => $cells) {
                $this->cellOverrides[$exerciseId] = $cells;
            }

            $weekData = $this->trainingPlan->config->get("users.{$this->user}.weeks", []);
            foreach ($weekData as $exerciseId => $weeks) {
                $this->weekOverrides[$exerciseId] = $weeks;
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

        if ($isDefaultUser) {
            if (! isset($this->defaultExerciseOverrides[$exerciseId])) {
                $this->defaultExerciseOverrides[$exerciseId] = [];
            }
            $this->defaultExerciseOverrides[$exerciseId][$field] = $value;
        } else {
            if (! isset($this->exerciseOverrides[$exerciseId])) {
                $this->exerciseOverrides[$exerciseId] = [];
            }
            $this->exerciseOverrides[$exerciseId][$field] = $value;
        }

        $this->persistExerciseOverride($exerciseId);
    }

    protected function persistExerciseOverride(int $exerciseId): void
    {
        $isDefaultUser = $this->user === null;
        $userId = $isDefaultUser ? 'default' : $this->user;
        $extraKey = "users.{$userId}.exercises.{$exerciseId}";

        $overrides = $isDefaultUser
            ? ($this->defaultExerciseOverrides[$exerciseId] ?? [])
            : ($this->exerciseOverrides[$exerciseId] ?? []);

        $this->trainingPlan->config->set($extraKey, $overrides);
        $this->trainingPlan->save();
    }

    public function getExerciseConfig(int $exerciseId, array $pivotExtra): array
    {
        $systemTarget = $this->targetGoal ?? $this->defaultData->targetGoal;
        $systemStartingReps = $pivotExtra['startingReps'] ?? ExerciseOverrideData::DEFAULT_STARTING_REPS;
        $systemSets = $pivotExtra['sets'] ?? ExerciseOverrideData::DEFAULT_SETS;
        $systemTut = $pivotExtra['tut'] ?? ExerciseOverrideData::DEFAULT_TUT;
        $systemRest = $pivotExtra['rest'] ?? ExerciseOverrideData::DEFAULT_REST;
        $oneRepMaxModifier = $pivotExtra['oneRepMaxModifier'] ?? 100;

        $defaultOverride = $this->defaultExerciseOverrides[$exerciseId] ?? [];
        $userOverride = $this->exerciseOverrides[$exerciseId] ?? [];

        $isDefaultUser = $this->user === null;

        if ($isDefaultUser) {
            return [
                'target' => $defaultOverride['target'] ?? $systemTarget,
                'startingReps' => $defaultOverride['startingReps'] ?? $systemStartingReps,
                'sets' => $defaultOverride['sets'] ?? $systemSets,
                'tut' => $defaultOverride['tut'] ?? $systemTut,
                'rest' => $defaultOverride['rest'] ?? $systemRest,
                'oneRepMaxModifier' => $oneRepMaxModifier,
                'hasTargetOverride' => isset($defaultOverride['target']),
                'hasStartingRepsOverride' => isset($defaultOverride['startingReps']),
                'hasSetsOverride' => isset($defaultOverride['sets']),
                'hasTutOverride' => isset($defaultOverride['tut']),
                'hasRestOverride' => isset($defaultOverride['rest']),
            ];
        }

        return [
            'target' => $userOverride['target'] ?? $defaultOverride['target'] ?? $systemTarget,
            'startingReps' => $userOverride['startingReps'] ?? $defaultOverride['startingReps'] ?? $systemStartingReps,
            'sets' => $userOverride['sets'] ?? $defaultOverride['sets'] ?? $systemSets,
            'tut' => $userOverride['tut'] ?? $defaultOverride['tut'] ?? $systemTut,
            'rest' => $userOverride['rest'] ?? $defaultOverride['rest'] ?? $systemRest,
            'oneRepMaxModifier' => $oneRepMaxModifier,
            'hasTargetOverride' => isset($userOverride['target']),
            'hasStartingRepsOverride' => isset($userOverride['startingReps']),
            'hasSetsOverride' => isset($userOverride['sets']),
            'hasTutOverride' => isset($userOverride['tut']),
            'hasRestOverride' => isset($userOverride['rest']),
        ];
    }

    public function generateBlock(int $exerciseId, array $pivotExtra, int $programId): ?TrainingBlock
    {
        $measuredWeight = $this->measuredWeight;
        $measuredReps = $this->measuredReps;

        if ($measuredWeight === null || $measuredWeight <= 0 || $measuredReps === null || $measuredReps < 1) {
            return null;
        }

        $config = $this->getExerciseConfig($exerciseId, $pivotExtra);
        $sessionsPerWeek = $this->getSessionsPerWeekForProgram($programId);

        $generator = new TrainingBlockGenerator;

        return $generator->generate(
            measuredWeight: $measuredWeight,
            measuredReps: $measuredReps,
            oneRepMaxModifier: $config['oneRepMaxModifier'],
            targetPercentage: $config['target'],
            startingReps: $config['startingReps'],
            sets: $config['sets'],
            weeks: $this->weeks,
            sessionsPerWeek: $sessionsPerWeek,
            deloadEnabled: true,
            deloadSetsReduction: 1,
        );
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
            $cellKey = "w{$weekIndex}-s{$s}-set{$setIndex}";

            if ($isDefaultUser) {
                if ($valuesMatch) {
                    if (isset($this->defaultCellOverrides[$exerciseId][$cellKey][$field])) {
                        unset($this->defaultCellOverrides[$exerciseId][$cellKey][$field]);
                        if (empty($this->defaultCellOverrides[$exerciseId][$cellKey])) {
                            unset($this->defaultCellOverrides[$exerciseId][$cellKey]);
                        }
                        if (empty($this->defaultCellOverrides[$exerciseId])) {
                            unset($this->defaultCellOverrides[$exerciseId]);
                        }
                    }
                } else {
                    if (! isset($this->defaultCellOverrides[$exerciseId])) {
                        $this->defaultCellOverrides[$exerciseId] = [];
                    }
                    if (! isset($this->defaultCellOverrides[$exerciseId][$cellKey])) {
                        $this->defaultCellOverrides[$exerciseId][$cellKey] = [];
                    }
                    $this->defaultCellOverrides[$exerciseId][$cellKey][$field] = $value;
                }
            } else {
                if ($valuesMatch) {
                    if (isset($this->cellOverrides[$exerciseId][$cellKey][$field])) {
                        unset($this->cellOverrides[$exerciseId][$cellKey][$field]);
                        if (empty($this->cellOverrides[$exerciseId][$cellKey])) {
                            unset($this->cellOverrides[$exerciseId][$cellKey]);
                        }
                        if (empty($this->cellOverrides[$exerciseId])) {
                            unset($this->cellOverrides[$exerciseId]);
                        }
                    }
                } else {
                    if (! isset($this->cellOverrides[$exerciseId])) {
                        $this->cellOverrides[$exerciseId] = [];
                    }
                    if (! isset($this->cellOverrides[$exerciseId][$cellKey])) {
                        $this->cellOverrides[$exerciseId][$cellKey] = [];
                    }
                    $this->cellOverrides[$exerciseId][$cellKey][$field] = $value;
                }
            }
        }

        $this->persistCellOverrides($exerciseId);
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
        $userId = $isDefaultUser ? 'default' : $this->user;
        $extraKey = "users.{$userId}.cells.{$exerciseId}";

        $overrides = $isDefaultUser
            ? ($this->defaultCellOverrides[$exerciseId] ?? [])
            : ($this->cellOverrides[$exerciseId] ?? []);

        if (empty($overrides)) {
            $this->trainingPlan->config->forget($extraKey);
        } else {
            $this->trainingPlan->config->set($extraKey, $overrides);
        }
        $this->trainingPlan->save();
    }

    public function getCellOverrides(int $exerciseId): array
    {
        $isDefaultUser = $this->user === null;

        if ($isDefaultUser) {
            return $this->defaultCellOverrides[$exerciseId] ?? [];
        }

        $defaultOverrides = $this->defaultCellOverrides[$exerciseId] ?? [];
        $userOverrides = $this->cellOverrides[$exerciseId] ?? [];

        return array_merge($defaultOverrides, $userOverrides);
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
        $weekKey = "w{$weekIndex}";

        $effectiveValue = $this->getEffectiveWeekValue($exerciseId, $weekIndex, $field);
        $valuesMatch = $this->weekValuesMatch($value, $effectiveValue, $field);

        if ($isDefaultUser) {
            if ($valuesMatch) {
                if (isset($this->defaultWeekOverrides[$exerciseId][$weekKey][$field])) {
                    unset($this->defaultWeekOverrides[$exerciseId][$weekKey][$field]);
                    if (empty($this->defaultWeekOverrides[$exerciseId][$weekKey])) {
                        unset($this->defaultWeekOverrides[$exerciseId][$weekKey]);
                    }
                    if (empty($this->defaultWeekOverrides[$exerciseId])) {
                        unset($this->defaultWeekOverrides[$exerciseId]);
                    }
                }
            } else {
                if (! isset($this->defaultWeekOverrides[$exerciseId])) {
                    $this->defaultWeekOverrides[$exerciseId] = [];
                }
                if (! isset($this->defaultWeekOverrides[$exerciseId][$weekKey])) {
                    $this->defaultWeekOverrides[$exerciseId][$weekKey] = [];
                }
                $this->defaultWeekOverrides[$exerciseId][$weekKey][$field] = $value;
            }
        } else {
            if ($valuesMatch) {
                if (isset($this->weekOverrides[$exerciseId][$weekKey][$field])) {
                    unset($this->weekOverrides[$exerciseId][$weekKey][$field]);
                    if (empty($this->weekOverrides[$exerciseId][$weekKey])) {
                        unset($this->weekOverrides[$exerciseId][$weekKey]);
                    }
                    if (empty($this->weekOverrides[$exerciseId])) {
                        unset($this->weekOverrides[$exerciseId]);
                    }
                }
            } else {
                if (! isset($this->weekOverrides[$exerciseId])) {
                    $this->weekOverrides[$exerciseId] = [];
                }
                if (! isset($this->weekOverrides[$exerciseId][$weekKey])) {
                    $this->weekOverrides[$exerciseId][$weekKey] = [];
                }
                $this->weekOverrides[$exerciseId][$weekKey][$field] = $value;
            }
        }

        $this->persistWeekOverrides($exerciseId);
    }

    protected function getEffectiveWeekValue(int $exerciseId, int $weekIndex, string $field): mixed
    {
        $extra = $this->findPivotExtraForExercise($exerciseId);
        $exercise = Exercise::find($exerciseId);
        $exerciseTypeConfig = $exercise?->config?->strength;

        $systemTut = $extra['tut'] ?? $exerciseTypeConfig?->timeUnderTension ?? ExerciseOverrideData::DEFAULT_TUT;
        $systemRest = $extra['rest'] ?? $exerciseTypeConfig?->rest ?? ExerciseOverrideData::DEFAULT_REST;

        $defaultExerciseOverride = $this->defaultExerciseOverrides[$exerciseId] ?? [];
        $defaultWeekOverride = $this->defaultWeekOverrides[$exerciseId]["w{$weekIndex}"] ?? [];

        $baseValue = match ($field) {
            'tut' => $defaultWeekOverride['tut'] ?? $defaultExerciseOverride['tut'] ?? $systemTut,
            'rest' => $defaultWeekOverride['rest'] ?? $defaultExerciseOverride['rest'] ?? $systemRest,
            default => null,
        };

        return $baseValue;
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
        $userId = $isDefaultUser ? 'default' : $this->user;
        $extraKey = "users.{$userId}.weeks.{$exerciseId}";

        $overrides = $isDefaultUser
            ? ($this->defaultWeekOverrides[$exerciseId] ?? [])
            : ($this->weekOverrides[$exerciseId] ?? []);

        if (empty($overrides)) {
            $this->trainingPlan->config->forget($extraKey);
        } else {
            $this->trainingPlan->config->set($extraKey, $overrides);
        }
        $this->trainingPlan->save();
    }

    public function getWeekOverrides(int $exerciseId): array
    {
        $isDefaultUser = $this->user === null;

        if ($isDefaultUser) {
            return $this->defaultWeekOverrides[$exerciseId] ?? [];
        }

        $defaultOverrides = $this->defaultWeekOverrides[$exerciseId] ?? [];
        $userOverrides = $this->weekOverrides[$exerciseId] ?? [];

        $merged = [];
        $allKeys = array_unique(array_merge(array_keys($defaultOverrides), array_keys($userOverrides)));

        foreach ($allKeys as $weekKey) {
            $merged[$weekKey] = array_merge(
                $defaultOverrides[$weekKey] ?? [],
                $userOverrides[$weekKey] ?? []
            );
        }

        return $merged;
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
        $userId = $isDefaultUser ? 'default' : $this->user;

        if ($isDefaultUser) {
            unset($this->defaultExerciseOverrides[$exerciseId]);
            unset($this->defaultCellOverrides[$exerciseId]);
            unset($this->defaultWeekOverrides[$exerciseId]);
        } else {
            unset($this->exerciseOverrides[$exerciseId]);
            unset($this->cellOverrides[$exerciseId]);
            unset($this->weekOverrides[$exerciseId]);
        }

        $this->trainingPlan->config->forget("users.{$userId}.exercises.{$exerciseId}");
        $this->trainingPlan->config->forget("users.{$userId}.cells.{$exerciseId}");
        $this->trainingPlan->config->forget("users.{$userId}.weeks.{$exerciseId}");
        $this->trainingPlan->save();
    }

    public function getWeekValue(int $exerciseId, int $weekIndex, string $field, array $config): mixed
    {
        $weekOverrides = $this->getWeekOverrides($exerciseId);
        $weekKey = "w{$weekIndex}";

        if (isset($weekOverrides[$weekKey][$field])) {
            return $weekOverrides[$weekKey][$field];
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

        foreach ($overrides as $cellKey => $values) {
            if (! preg_match('/^w(\d+)-s(\d+)-set(\d+)$/', $cellKey, $matches)) {
                continue;
            }

            $weekIndex = (int) $matches[1];
            $sessionIndex = (int) $matches[2];
            $setIndex = (int) $matches[3];

            if (! isset($weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex])) {
                continue;
            }

            $set = $weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex];

            $newReps = $values['reps'] ?? $set->reps;
            $newWeight = $values['weight'] ?? $set->weight;

            $weeks[$weekIndex]->sessions[$sessionIndex]->sets[$setIndex] = new \App\Training\Data\TrainingSet(
                reps: $newReps,
                weight: $newWeight,
                oneRepMax: $set->oneRepMax,
            );
        }

        return $block->withWeeks($weeks);
    }

    public function render()
    {
        return view('livewire.training.view.plan');
    }
}
