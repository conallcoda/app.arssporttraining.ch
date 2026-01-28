<?php

namespace App\Livewire\Training\View;

use App\Livewire\Concerns\InteractsWithParentView;
use App\Models\Training\Progression\Reference\RepPercentageTable;
use App\Models\TrainingPlan;
use App\Models\Users\User;
use App\Training\Data\ExerciseOverrideData;
use App\Training\Data\TrainingBlock;
use App\Training\Services\TrainingBlockGenerator;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class Plan extends Component
{
    use InteractsWithParentView;

    public TrainingPlan $trainingPlan;

    public Collection $programs;

    public Collection $users;

    #[Url]
    public ?int $user = 0;

    public ?int $measured_reps = null;

    public ?float $measured_weight = null;

    public ?int $target_goal = null;

    public array $exerciseOverrides = [];

    public array $defaultExerciseOverrides = [];

    public array $cellOverrides = [];

    public array $defaultCellOverrides = [];

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

    public function userHasMeasuredData(int $userId): bool
    {
        $key = $userId === 0 ? 'default' : (string) $userId;
        $data = $this->trainingPlan->extra->get("users.{$key}.training_plan", []);

        $measuredReps = $data['measured_reps'] ?? null;
        $measuredWeight = $data['measured_weight'] ?? null;

        return $measuredReps !== null && $measuredWeight !== null;
    }

    public function countUserOverrides(int $userId): int
    {
        $key = $userId === 0 ? 'default' : (string) $userId;

        $exerciseOverrides = $this->trainingPlan->extra->get("users.{$key}.exercises", []);
        $cellOverrides = $this->trainingPlan->extra->get("users.{$key}.cells", []);

        $count = 0;

        foreach ($exerciseOverrides as $overrides) {
            $count += count($overrides);
        }

        foreach ($cellOverrides as $exerciseCells) {
            foreach ($exerciseCells as $cellValues) {
                $count += count($cellValues);
            }
        }

        return $count;
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        if ($this->user === null || $this->user === 0) {
            return null;
        }

        return $this->users->firstWhere('id', $this->user);
    }

    #[Computed]
    public function defaultData(): AthleteTrainingProgramData
    {
        return AthleteTrainingProgramData::fromTrainingPlan($this->trainingPlan, null);
    }

    #[Computed]
    public function estimatedOneRepMax(): ?float
    {
        $reps = $this->measured_reps ?? ($this->user === 0 ? AthleteTrainingProgramData::DEFAULT_MEASURED_REPS : null);
        $weight = $this->measured_weight ?? ($this->user === 0 ? AthleteTrainingProgramData::DEFAULT_MEASURED_WEIGHT : null);

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
        $goal = $this->target_goal ?? ($this->user === 0 ? AthleteTrainingProgramData::DEFAULT_TARGET_GOAL : $this->defaultData->target_goal);

        if ($startingMax === null || $goal === null) {
            return null;
        }

        return round($startingMax * (1 + $goal / 100), 1);
    }

    public function getPivotExtra(int $programId, int $exerciseId): array
    {
        $pivot = \App\Models\TrainingPlanProgramExercise::query()
            ->where('training_plan_program_id', $programId)
            ->where('exercise_id', $exerciseId)
            ->first();

        if (! $pivot) {
            return [];
        }

        $extra = $pivot->extra;

        if ($extra instanceof \Spatie\SchemalessAttributes\SchemalessAttributes) {
            return $extra->all();
        }

        if (is_array($extra)) {
            return $extra;
        }

        return [];
    }

    protected function findPivotExtraForExercise(int $exerciseId): array
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

        $extra = $pivot->extra;

        if ($extra instanceof \Spatie\SchemalessAttributes\SchemalessAttributes) {
            return $extra->all();
        }

        if (is_array($extra)) {
            return $extra;
        }

        return [];
    }

    protected function getEffectiveCellValue(int $exerciseId, int $weekIndex, int $sessionIndex, int $setIndex, string $field): mixed
    {
        $extra = $this->findPivotExtraForExercise($exerciseId);
        $pivotExtra = [
            'oneRepMaxModifier' => $extra['oneRepMaxModifier'] ?? 100,
            'startingReps' => $extra['startingReps'] ?? null,
            'sets' => $extra['sets'] ?? null,
        ];

        $block = $this->generateBlock($exerciseId, $pivotExtra);

        if (! $block) {
            return null;
        }

        $isDefaultUser = $this->user === 0;

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
        return $this->trainingPlan->duration ?? 5;
    }

    public function selectUser(int $userId): void
    {
        $this->user = $userId;
        $this->loadAthleteData();
        $this->dispatch('plan-user-changed', userId: $userId);
    }

    public function loadAthleteData(): void
    {
        if ($this->user === null) {
            $this->measured_reps = null;
            $this->measured_weight = null;
            $this->target_goal = null;
            $this->exerciseOverrides = [];

            return;
        }

        $userId = $this->user === 0 ? null : $this->user;
        $data = AthleteTrainingProgramData::fromTrainingPlan($this->trainingPlan, $userId);

        $this->measured_reps = $data->measured_reps;
        $this->measured_weight = $data->measured_weight;
        $this->target_goal = $data->target_goal;

        $this->loadExerciseOverrides();
    }

    protected function loadExerciseOverrides(): void
    {
        $this->exerciseOverrides = [];
        $this->defaultExerciseOverrides = [];
        $this->cellOverrides = [];
        $this->defaultCellOverrides = [];

        if ($this->user === null) {
            return;
        }

        $defaultExercisesData = $this->trainingPlan->extra->get('users.default.exercises', []);
        foreach ($defaultExercisesData as $exerciseId => $overrideData) {
            $this->defaultExerciseOverrides[$exerciseId] = $overrideData;
        }

        $defaultCellData = $this->trainingPlan->extra->get('users.default.cells', []);
        foreach ($defaultCellData as $exerciseId => $cells) {
            $this->defaultCellOverrides[$exerciseId] = $cells;
        }

        if ($this->user !== 0) {
            $exercisesData = $this->trainingPlan->extra->get("users.{$this->user}.exercises", []);

            foreach ($exercisesData as $exerciseId => $overrideData) {
                $this->exerciseOverrides[$exerciseId] = $overrideData;
            }

            $cellData = $this->trainingPlan->extra->get("users.{$this->user}.cells", []);
            foreach ($cellData as $exerciseId => $cells) {
                $this->cellOverrides[$exerciseId] = $cells;
            }
        }
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['measured_reps', 'measured_weight', 'target_goal'])) {
            return;
        }

        if ($this->user === null) {
            return;
        }

        $userId = $this->user === 0 ? null : $this->user;

        $data = new AthleteTrainingProgramData(
            measured_reps: $this->measured_reps,
            measured_weight: $this->measured_weight,
            target_goal: $this->target_goal,
        );

        $data->persist($this->trainingPlan, $userId);
        $this->trainingPlan->refresh();
    }

    public function updateExerciseOverride(int $exerciseId, string $field, mixed $value): void
    {
        if ($this->user === null) {
            return;
        }

        $isDefaultUser = $this->user === 0;

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
        $isDefaultUser = $this->user === 0;
        $userId = $isDefaultUser ? 'default' : $this->user;
        $extraKey = "users.{$userId}.exercises.{$exerciseId}";

        $overrides = $isDefaultUser
            ? ($this->defaultExerciseOverrides[$exerciseId] ?? [])
            : ($this->exerciseOverrides[$exerciseId] ?? []);

        $this->trainingPlan->extra->set($extraKey, $overrides);
        $this->trainingPlan->save();
    }

    public function getExerciseConfig(int $exerciseId, array $pivotExtra): array
    {
        $systemTarget = $this->defaultData->target_goal ?? ExerciseOverrideData::DEFAULT_TARGET;
        $systemStartingReps = $pivotExtra['startingReps'] ?? ExerciseOverrideData::DEFAULT_STARTING_REPS;
        $systemSets = $pivotExtra['sets'] ?? ExerciseOverrideData::DEFAULT_SETS;
        $oneRepMaxModifier = $pivotExtra['oneRepMaxModifier'] ?? 100;

        $defaultOverride = $this->defaultExerciseOverrides[$exerciseId] ?? [];
        $userOverride = $this->exerciseOverrides[$exerciseId] ?? [];

        $isDefaultUser = $this->user === 0;

        if ($isDefaultUser) {
            return [
                'target' => $defaultOverride['target'] ?? $systemTarget,
                'startingReps' => $defaultOverride['startingReps'] ?? $systemStartingReps,
                'sets' => $defaultOverride['sets'] ?? $systemSets,
                'oneRepMaxModifier' => $oneRepMaxModifier,
                'hasTargetOverride' => isset($defaultOverride['target']),
                'hasStartingRepsOverride' => isset($defaultOverride['startingReps']),
                'hasSetsOverride' => isset($defaultOverride['sets']),
            ];
        }

        return [
            'target' => $userOverride['target'] ?? $defaultOverride['target'] ?? $systemTarget,
            'startingReps' => $userOverride['startingReps'] ?? $defaultOverride['startingReps'] ?? $systemStartingReps,
            'sets' => $userOverride['sets'] ?? $defaultOverride['sets'] ?? $systemSets,
            'oneRepMaxModifier' => $oneRepMaxModifier,
            'hasTargetOverride' => isset($userOverride['target']),
            'hasStartingRepsOverride' => isset($userOverride['startingReps']),
            'hasSetsOverride' => isset($userOverride['sets']),
        ];
    }

    public function generateBlock(int $exerciseId, array $pivotExtra): ?TrainingBlock
    {
        $measuredWeight = $this->measured_weight;
        $measuredReps = $this->measured_reps;

        if ($measuredWeight === null || $measuredWeight <= 0 || $measuredReps === null || $measuredReps < 1) {
            return null;
        }

        $config = $this->getExerciseConfig($exerciseId, $pivotExtra);

        $generator = new TrainingBlockGenerator;

        return $generator->generate(
            measuredWeight: $measuredWeight,
            measuredReps: $measuredReps,
            oneRepMaxModifier: $config['oneRepMaxModifier'],
            targetPercentage: $config['target'],
            startingReps: $config['startingReps'],
            sets: $config['sets'],
            weeks: $this->weeks,
            sessionsPerWeek: 2,
            deloadEnabled: true,
            deloadSetsReduction: 1,
        );
    }

    public function getPlaceholder(string $field): mixed
    {
        if ($this->user === 0 || $this->user === null) {
            return null;
        }

        if ($field !== 'target_goal') {
            return null;
        }

        return $this->defaultData->{$field};
    }

    public function updateCellOverride(int $exerciseId, int $weekIndex, int $sessionIndex, int $setIndex, string $field, mixed $value): void
    {
        if ($this->user === null) {
            return;
        }

        $isDefaultUser = $this->user === 0;
        $sessionsPerWeek = 2;

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
        $isDefaultUser = $this->user === 0;
        $userId = $isDefaultUser ? 'default' : $this->user;
        $extraKey = "users.{$userId}.cells.{$exerciseId}";

        $overrides = $isDefaultUser
            ? ($this->defaultCellOverrides[$exerciseId] ?? [])
            : ($this->cellOverrides[$exerciseId] ?? []);

        if (empty($overrides)) {
            $this->trainingPlan->extra->forget($extraKey);
        } else {
            $this->trainingPlan->extra->set($extraKey, $overrides);
        }
        $this->trainingPlan->save();
    }

    public function getCellOverrides(int $exerciseId): array
    {
        $isDefaultUser = $this->user === 0;

        if ($isDefaultUser) {
            return $this->defaultCellOverrides[$exerciseId] ?? [];
        }

        $defaultOverrides = $this->defaultCellOverrides[$exerciseId] ?? [];
        $userOverrides = $this->cellOverrides[$exerciseId] ?? [];

        return array_merge($defaultOverrides, $userOverrides);
    }

    public function getUserSpecificCellOverrides(int $exerciseId): array
    {
        $isDefaultUser = $this->user === 0;

        if ($isDefaultUser) {
            return $this->defaultCellOverrides[$exerciseId] ?? [];
        }

        return $this->cellOverrides[$exerciseId] ?? [];
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
