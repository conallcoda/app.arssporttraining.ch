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

    #[Url]
    public ?int $user = null;

    public ?int $measured_reps = null;

    public ?float $measured_weight = null;

    public ?int $target_goal = null;

    public array $exerciseOverrides = [];

    public array $defaultExerciseOverrides = [];

    public function mount(TrainingPlan $trainingPlan): void
    {
        $this->trainingPlan = $trainingPlan;
        $this->loadAthleteData();
    }

    #[Computed]
    public function users(): Collection
    {
        return $this->trainingPlan->allUsers()
            ->orderBy('forename')
            ->orderBy('surname')
            ->get();
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

    #[Computed]
    public function programs(): Collection
    {
        return $this->trainingPlan->programs()
            ->with(['exercises' => function ($query) {
                $query->orderByPivot('sort');
            }])
            ->orderBy('sort')
            ->get();
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

    #[Computed]
    public function weeks(): int
    {
        return $this->trainingPlan->duration ?? 5;
    }

    public function selectUser(int $userId): void
    {
        $this->user = $userId;
        $this->loadAthleteData();
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

        if ($this->user === null) {
            return;
        }

        $defaultExercisesData = $this->trainingPlan->extra->get('users.default.exercises', []);
        foreach ($defaultExercisesData as $exerciseId => $overrideData) {
            $this->defaultExerciseOverrides[$exerciseId] = $overrideData;
        }

        if ($this->user !== 0) {
            $exercisesData = $this->trainingPlan->extra->get("users.{$this->user}.exercises", []);

            foreach ($exercisesData as $exerciseId => $overrideData) {
                $this->exerciseOverrides[$exerciseId] = $overrideData;
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

    public function render()
    {
        return view('livewire.training.view.plan');
    }
}
