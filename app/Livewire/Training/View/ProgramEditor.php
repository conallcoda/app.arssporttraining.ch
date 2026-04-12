<?php

namespace App\Livewire\Training\View;

use App\Data\Exercise\BilateralReps;
use App\Data\Exercise\Settings\DistanceSetting;
use App\Data\Exercise\Settings\DurationSetting;
use App\Data\Exercise\Settings\HeartRateSetting;
use App\Data\Exercise\Settings\HeartRateZoneSetting;
use App\Data\Exercise\Settings\PaceSetting;
use App\Data\Exercise\Settings\RepsSetting;
use App\Data\Exercise\Settings\RestSetting;
use App\Data\Exercise\Settings\SetsSetting;
use App\Data\Exercise\Settings\TempoSetting;
use App\Data\Exercise\Settings\WattsSetting;
use App\Data\Exercise\Settings\WeightSetting;
use App\Data\Training\Config\ExerciseOverrides;
use App\Form\Fields\Exercise\Exercises;
use App\Form\Fields\Training\Program\SelectProgram;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use Coda\Cms\Livewire\Concerns\InteractsWithFormData;
use Coda\FormKit\Form;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ProgramEditor extends Component
{
    use InteractsWithFormData {
        InteractsWithFormData::updated as traitUpdated;
    }

    public ExerciseProgram $exerciseProgram;

    public int $weeks = 5;

    public bool $showWeeksInput = false;

    public int $sessionsPerWeek = 1;

    public array $weekLabels = [];

    public array $weekSessions = [];

    public array $expandedWeeks = [];

    public array $lockedSessionsByWeek = [];

    public bool $sessionLabels = false;

    public bool $showNameInput = false;

    public string $gridLayout = 'side-by-side';

    public int $planId;

    public string $planType = ExerciseProgram::class;

    public ?int $userId = null;

    public ?int $planMeasuredReps = null;

    public ?float $planMeasuredWeight = null;

    public int|float|null $planTargetGoal = 10;

    public ?int $planMaxHR = null;

    public ?int $planIatPercent = null;

    public ?string $planBlockGoalLabel = null;

    public ?string $plan1rmLabel = null;

    public ?string $planHeartRateLabel = null;

    public bool $hasAutoWeightExercises = false;

    public bool $hasHeartRateExercises = false;

    public bool $planHasBlock = false;

    public array $planGroupMemberMetrics = [];

    public array $data = [];

    public function mount(
        ExerciseProgram $exerciseProgram,
        int $planId,
        int $weeks = 5,
        bool $showWeeksInput = false,
        int $sessionsPerWeek = 1,
        string $planType = ExerciseProgram::class,
        ?int $userId = null,
        ?int $planMeasuredReps = null,
        ?float $planMeasuredWeight = null,
        int|float|null $planTargetGoal = 10,
        ?int $planMaxHR = null,
        ?int $planIatPercent = null,
        array $weekLabels = [],
        array $weekSessions = [],
        array $expandedWeeks = [],
        array $lockedSessionsByWeek = [],
        bool $sessionLabels = false,
        string $gridLayout = 'side-by-side',
        ?string $planBlockGoalLabel = null,
        ?string $plan1rmLabel = null,
        ?string $planHeartRateLabel = null,
        bool $hasAutoWeightExercises = false,
        bool $hasHeartRateExercises = false,
        bool $planHasBlock = false,
        array $planGroupMemberMetrics = [],
    ): void {
        $this->exerciseProgram = $exerciseProgram;
        $this->planId = $planId;
        $this->showWeeksInput = $showWeeksInput;
        $this->weeks = $showWeeksInput ? $exerciseProgram->config->weeks : $weeks;
        $this->sessionsPerWeek = $sessionsPerWeek;
        $this->planType = $planType;
        $this->userId = $userId;
        $this->planMeasuredReps = $planMeasuredReps;
        $this->planMeasuredWeight = $planMeasuredWeight;
        $this->planTargetGoal = $planTargetGoal;
        $this->planMaxHR = $planMaxHR;
        $this->planIatPercent = $planIatPercent;
        $this->weekLabels = $weekLabels;
        $this->weekSessions = $weekSessions;
        $this->expandedWeeks = $expandedWeeks;
        $this->lockedSessionsByWeek = $lockedSessionsByWeek;
        $this->sessionLabels = $sessionLabels;
        $this->gridLayout = $gridLayout;
        $this->planBlockGoalLabel = $planBlockGoalLabel;
        $this->plan1rmLabel = $plan1rmLabel;
        $this->planHeartRateLabel = $planHeartRateLabel;
        $this->hasAutoWeightExercises = $hasAutoWeightExercises;
        $this->hasHeartRateExercises = $hasHeartRateExercises;
        $this->planHasBlock = $planHasBlock;
        $this->planGroupMemberMetrics = $planGroupMemberMetrics;
        $this->loadExerciseData();
    }

    protected function loadExerciseData(): void
    {
        $this->exerciseProgram->loadMissing([
            'exercises' => fn ($q) => $q->orderByPivot('sort'),
        ]);

        $this->data = [
            'exercises' => $this->exerciseProgram->exercises->map(fn ($e) => [
                'id' => $e->id,
                '_key' => uniqid('item_', true),
                'sort' => $e->pivot->sort ?? 0,
                'group' => $e->pivot->group,
            ])->values()->all(),
            'warm_up_program_id' => $this->exerciseProgram->warm_up_program_id,
            'warm_down_program_id' => $this->exerciseProgram->warm_down_program_id,
        ];
    }

    #[Computed]
    public function formConfig(): Form
    {
        return Form::make()
            ->fieldset('Exercises', [
                Exercises::make('exercises')->withOptions()->groupable(),
            ]);
    }

    #[Computed]
    public function fields(): array
    {
        return $this->formConfig->getFields();
    }

    #[Computed]
    public function fieldsets(): array
    {
        return $this->formConfig->resolveFieldsets($this->data);
    }

    #[Computed]
    public function exercises(): Collection
    {
        return $this->exerciseProgram->exercises()
            ->orderByPivot('sort')
            ->get();
    }

    #[Computed]
    public function exerciseGroupLabels(): array
    {
        $labels = [];
        $groupCounters = [];

        foreach ($this->exercises as $exercise) {
            $group = $exercise->pivot->group;

            if ($group) {
                if (! isset($groupCounters[$group])) {
                    $groupCounters[$group] = 0;
                }
                $groupCounters[$group]++;
                $labels[$exercise->id] = $group.$groupCounters[$group];
            }
        }

        return $labels;
    }

    public function updated(string $property, mixed $value): void
    {
        $this->traitUpdated($property, $value);

        if (str_starts_with($property, 'data.exercises.') || $property === 'data.exercises') {
            $hasCompleteExercises = collect($this->data['exercises'] ?? [])
                ->contains(fn ($item) => ! empty($item['id']));

            if ($hasCompleteExercises) {
                $this->saveExercises();
            }
        }
    }

    public function updatedWeeks(): void
    {
        if (! $this->showWeeksInput) {
            return;
        }

        $config = $this->exerciseProgram->config;
        $config->weeks = $this->weeks;
        $this->exerciseProgram->config = $config;
        $this->exerciseProgram->save();
    }

    public function removeRelationshipItem(string $fieldName, int $index): void
    {
        if (! isset($this->data[$fieldName][$index])) {
            return;
        }

        unset($this->data[$fieldName][$index]);
        $this->data[$fieldName] = array_values($this->data[$fieldName]);

        $field = collect($this->getAllFields())->firstWhere('name', $fieldName);
        if ($field?->sortable) {
            foreach ($this->data[$fieldName] as $i => $item) {
                $this->data[$fieldName][$i]['sort'] = $i;
            }
        }

        if ($fieldName === 'exercises') {
            $this->saveExercises();
        }
    }

    public function moveRelationshipItem(string $fieldName, int $index, int $direction): void
    {
        if (! isset($this->data[$fieldName])) {
            return;
        }

        $newIndex = $index + $direction;
        if ($newIndex < 0 || $newIndex >= count($this->data[$fieldName])) {
            return;
        }

        $items = $this->data[$fieldName];
        [$items[$index], $items[$newIndex]] = [$items[$newIndex], $items[$index]];

        $field = collect($this->getAllFields())->firstWhere('name', $fieldName);
        if ($field?->sortable) {
            foreach ($items as $i => $item) {
                $items[$i]['sort'] = $i;
            }
        }

        $this->data[$fieldName] = $items;

        if ($fieldName === 'exercises') {
            $this->saveExercises();
        }
    }

    public function saveExercises(): void
    {
        $currentExerciseIds = $this->exerciseProgram->exercises()->pluck('exercises.id')->toArray();
        $newExerciseIds = collect($this->data['exercises'] ?? [])
            ->filter(fn ($exercise) => ! empty($exercise['id']))
            ->pluck('id')
            ->toArray();

        $exercisesToAdd = array_diff($newExerciseIds, $currentExerciseIds);
        $exercisesToRemove = array_diff($currentExerciseIds, $newExerciseIds);

        ExerciseProgramExercise::where('exercise_program_id', $this->exerciseProgram->id)
            ->whereIn('exercise_id', $exercisesToRemove)
            ->delete();

        $config = $this->exerciseProgram->config;
        $configChanged = false;

        foreach ($exercisesToRemove as $exerciseId) {
            $config->removeExerciseOverrides($exerciseId);
            $configChanged = true;
        }

        foreach ($this->data['exercises'] as $index => $exerciseData) {
            if (empty($exerciseData['id'])) {
                continue;
            }

            $exerciseId = $exerciseData['id'];
            $sort = $exerciseData['sort'] ?? $index;

            $group = ! empty($exerciseData['group']) ? $exerciseData['group'] : null;

            if (in_array($exerciseId, $exercisesToAdd)) {
                ExerciseProgramExercise::create([
                    'exercise_program_id' => $this->exerciseProgram->id,
                    'exercise_id' => $exerciseId,
                    'sort' => $sort,
                    'group' => $group,
                ]);

                $exercise = Exercise::find($exerciseId);
                if ($exercise) {
                    $configArray = json_decode($exercise->getRawOriginal('config') ?? '{}', true) ?: [];
                    $config->setDefaultExerciseOverrides($exerciseId, $this->buildExerciseOverrides($configArray));
                    $configChanged = true;
                }
            } else {
                ExerciseProgramExercise::where('exercise_program_id', $this->exerciseProgram->id)
                    ->where('exercise_id', $exerciseId)
                    ->update(['sort' => $sort, 'group' => $group]);
            }
        }

        if ($configChanged) {
            $this->exerciseProgram->config = $config;
            $this->exerciseProgram->save();
        }

        $this->exerciseProgram->refresh();
        unset($this->exercises, $this->exerciseGroupLabels);
        $this->loadExerciseData();
    }

    protected function buildExerciseOverrides(array $configArray): ExerciseOverrides
    {
        return new ExerciseOverrides(
            settings: $configArray['settings'] ?? null,
            sets: isset($configArray['sets']) ? new SetsSetting(
                deload: $configArray['sets']['deload'] ?? 'none',
                deloadBy: $configArray['sets']['deloadBy'] ?? 1,
                label: $configArray['sets']['label'] ?? 'Set',
                default: $configArray['sets']['default'] ?? 4,
            ) : null,
            reps: isset($configArray['reps']) ? new RepsSetting(
                mode: $configArray['reps']['mode'] ?? 'manual',
                default: BilateralReps::parse($configArray['reps']['default'] ?? 10)->total(),
                stepDownInterval: $configArray['reps']['stepDownInterval'] ?? 2,
                decrement: $configArray['reps']['decrement'] ?? 2,
                minimum: $configArray['reps']['minimum'] ?? 1,
                label: $configArray['reps']['label'] ?? '',
                applyPer: $configArray['reps']['applyPer'] ?? 'session',
            ) : null,
            weight: isset($configArray['weight']) ? new WeightSetting(
                mode: $configArray['weight']['mode'] ?? 'manual',
                oneRepMaxModifier: $configArray['weight']['oneRepMaxModifier'] ?? 100,
                default: (float) ($configArray['weight']['default'] ?? 5),
                applyPer: $configArray['weight']['applyPer'] ?? 'session',
            ) : null,
            tempo: isset($configArray['tempo']) ? new TempoSetting(
                default: $configArray['tempo']['default'] ?? '3010',
                applyPer: $configArray['tempo']['applyPer'] ?? 'week',
            ) : null,
            rest: isset($configArray['rest']) ? new RestSetting(
                default: $configArray['rest']['default'] ?? 60,
                applyPer: $configArray['rest']['applyPer'] ?? 'week',
            ) : null,
            distance: isset($configArray['distance']) ? new DistanceSetting(
                unit: $configArray['distance']['unit'] ?? 'meters',
                default: $configArray['distance']['default'] ?? 500,
                applyPer: $configArray['distance']['applyPer'] ?? 'session',
            ) : null,
            duration: isset($configArray['duration']) ? new DurationSetting(
                unit: $configArray['duration']['unit'] ?? 'seconds',
                default: $configArray['duration']['default'] ?? 60,
                applyPer: $configArray['duration']['applyPer'] ?? 'session',
            ) : null,
            heartRate: isset($configArray['heartRate']) ? new HeartRateSetting(
                default: $configArray['heartRate']['default'] ?? '140',
                applyPer: $configArray['heartRate']['applyPer'] ?? 'session',
            ) : null,
            heartRateZone: isset($configArray['heartRateZone']) ? new HeartRateZoneSetting(
                default: $configArray['heartRateZone']['default'] ?? '3',
                applyPer: $configArray['heartRateZone']['applyPer'] ?? 'session',
            ) : null,
            pace: isset($configArray['pace']) ? new PaceSetting(
                default: $configArray['pace']['default'] ?? '5:00',
                applyPer: $configArray['pace']['applyPer'] ?? 'session',
            ) : null,
            watts: isset($configArray['watts']) ? new WattsSetting(
                default: $configArray['watts']['default'] ?? 100,
                applyPer: $configArray['watts']['applyPer'] ?? 'session',
            ) : null,
            gridOverrides: $configArray['overrides'] ?? ['cells' => [], 'weeks' => []],
        );
    }

    #[Computed]
    public function warmProgramsForm(): Form
    {
        return Form::make()
            ->fieldset('Warm Up', [
                SelectProgram::make('warm_up_program_id')->label('Warm Up')->withOptions(fn ($q) => $q->whereNull('parent_type')->whereNull('parent_id')->where('id', '!=', $this->exerciseProgram->id)),
            ])
            ->fieldset('Warm Down', [
                SelectProgram::make('warm_down_program_id')->label('Warm Down')->withOptions(fn ($q) => $q->whereNull('parent_type')->whereNull('parent_id')->where('id', '!=', $this->exerciseProgram->id)),
            ]);
    }

    #[Computed]
    public function warmProgramFieldsets(): array
    {
        return $this->warmProgramsForm->resolveFieldsets($this->data);
    }

    public function updatedDataWarmUpProgramId(): void
    {
        $this->exerciseProgram->update([
            'warm_up_program_id' => $this->data['warm_up_program_id'] ?: null,
        ]);
    }

    public function updatedDataWarmDownProgramId(): void
    {
        $this->exerciseProgram->update([
            'warm_down_program_id' => $this->data['warm_down_program_id'] ?: null,
        ]);
    }

    #[Computed]
    public function showAthleteContext(): bool
    {
        return ($this->hasAutoWeightExercises && $this->planHasBlock) || $this->hasHeartRateExercises;
    }

    #[On('exercise-overrides-changed')]
    public function onExerciseOverridesChanged(): void
    {
        $this->exerciseProgram->refresh();
    }

    public function render()
    {
        return view('livewire.training.view.program-editor');
    }
}
