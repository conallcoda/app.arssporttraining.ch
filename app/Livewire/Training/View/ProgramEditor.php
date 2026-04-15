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
use App\Data\Training\Config\ExercisePlanConfig;
use App\Form\Fields\Exercise\Exercises;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Exercise\ExerciseProgramTypeEnum;
use App\Training\ExerciseGroupLabeler;
use App\Training\TrainingSessionRebuildService;
use Coda\Cms\Livewire\Concerns\InteractsWithFormData;
use Coda\FormKit\Form;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ProgramEditor extends Component
{
    use InteractsWithFormData {
        InteractsWithFormData::updated as traitUpdated;
    }

    private const SECTION_TYPES = ['main', 'warm_up', 'warm_down'];

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

    public string $activeSection = 'main';

    public ?int $importProgramId = null;

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
        if ($exerciseProgram->type !== ExerciseProgramTypeEnum::Program) {
            $this->activeSection = 'main';
        }
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
        $this->exerciseProgram->unsetRelation('exercises');
        $this->exerciseProgram->load([
            'exercises' => fn ($q) => $q->orderByPivot('type')->orderByPivot('sort')->orderByPivot('id'),
        ]);

        foreach (self::SECTION_TYPES as $type) {
            $this->data[$this->sectionFieldName($type)] = $this->serializeSectionExercises($type);
        }

        $this->syncSectionFormData();
    }

    protected function serializeSectionExercises(string $type): array
    {
        return $this->exerciseProgram->exercises
            ->filter(fn (Exercise $exercise) => ($exercise->pivot->type ?? 'main') === $type)
            ->sortBy(fn (Exercise $exercise) => [$exercise->pivot->sort ?? 0, $exercise->pivot->id ?? 0])
            ->values()
            ->map(fn (Exercise $exercise) => [
                'id' => $exercise->id,
                'program_exercise_id' => $exercise->pivot->id,
                '_key' => uniqid('item_', true),
                'sort' => $exercise->pivot->sort ?? 0,
                'group' => $exercise->pivot->group,
            ])
            ->all();
    }

    protected function syncSectionFormData(): void
    {
        $this->data['section_exercises'] = $this->data[$this->sectionFieldName($this->activeSection)] ?? [];
    }

    protected function sectionFieldName(string $type): string
    {
        return $type.'_exercises';
    }

    #[Computed]
    public function formConfig(): Form
    {
        return Form::make()
            ->fieldset('Exercises', [
                Exercises::make('section_exercises')->withOptions()->groupable(),
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
            ->wherePivot('type', $this->activeSection)
            ->orderByPivot('sort')
            ->orderByPivot('id')
            ->get();
    }

    #[Computed]
    public function exerciseGroupLabels(): array
    {
        return ExerciseGroupLabeler::label(
            $this->exercises,
            fn (Exercise $exercise): ?string => $exercise->pivot->group,
            fn (Exercise $exercise): int => $exercise->pivot->id,
        );
    }

    #[Computed]
    public function importProgramOptions(): array
    {
        return ExerciseProgram::query()
            ->whereNull('exercise_programs.owner_id')
            ->whereNull('exercise_programs.parent_id')
            ->whereNull('exercise_programs.parent_type')
            ->where('exercise_programs.type', $this->importProgramType()->value)
            ->where('id', '!=', $this->exerciseProgram->id)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    #[Computed]
    public function availableSections(): array
    {
        return match ($this->programType) {
            ExerciseProgramTypeEnum::WarmUp, ExerciseProgramTypeEnum::WarmDown => ['main'],
            default => self::SECTION_TYPES,
        };
    }

    #[Computed]
    public function showSectionTabs(): bool
    {
        return count($this->availableSections) > 1;
    }

    #[Computed]
    public function programType(): ExerciseProgramTypeEnum
    {
        return $this->exerciseProgram->type ?? ExerciseProgramTypeEnum::Program;
    }

    public function updated(string $property, mixed $value): void
    {
        $this->traitUpdated($property, $value);

        if (str_starts_with($property, 'data.section_exercises.') || $property === 'data.section_exercises') {
            $this->data[$this->sectionFieldName($this->activeSection)] = $this->data['section_exercises'] ?? [];

            $hasCompleteExercises = collect($this->data['section_exercises'] ?? [])
                ->contains(fn ($item) => ! empty($item['id']));

            if ($hasCompleteExercises) {
                $this->saveSectionExercises();
            }
        }
    }

    public function updatedActiveSection(): void
    {
        if (! in_array($this->activeSection, $this->availableSections, true)) {
            $this->activeSection = $this->availableSections[0] ?? 'main';
        }

        $this->syncSectionFormData();
        unset($this->fieldsets, $this->exercises, $this->exerciseGroupLabels);
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

        if ($fieldName === 'section_exercises') {
            $this->data[$this->sectionFieldName($this->activeSection)] = $this->data[$fieldName];
            $this->saveSectionExercises();
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

        if ($fieldName === 'section_exercises') {
            $this->data[$this->sectionFieldName($this->activeSection)] = $items;
            $this->saveSectionExercises();
        }
    }

    public function importSectionExercises(): void
    {
        if ($this->importProgramId === null) {
            return;
        }

        Flux::modal($this->importConfirmModalName())->show();
    }

    public function confirmImportSectionExercises(): void
    {
        if ($this->importProgramId === null) {
            return;
        }

        $sourceProgram = ExerciseProgram::query()
            ->with([
                'exercises' => fn ($q) => $q->orderByPivot('type')->orderByPivot('sort')->orderByPivot('id'),
            ])
            ->findOrFail($this->importProgramId);

        $sourceSection = $this->importSourceSection($sourceProgram);
        $sourceRows = $sourceProgram->exercises
            ->filter(fn (Exercise $exercise) => ($exercise->pivot->type ?? 'main') === $sourceSection)
            ->sortBy(fn (Exercise $exercise) => [$exercise->pivot->sort ?? 0, $exercise->pivot->id ?? 0])
            ->values();

        $config = $this->exerciseProgram->config;
        $currentRows = $this->exerciseProgram->exercises()
            ->wherePivot('type', $this->activeSection)
            ->get()
            ->keyBy(fn (Exercise $exercise) => (int) $exercise->pivot->id);

        foreach ($currentRows->keys() as $programExerciseId) {
            $config->removeExerciseOverrides((int) $programExerciseId);

            foreach (array_keys($config->userExercises) as $userId) {
                unset($config->userExercises[$userId][(int) $programExerciseId]);
            }
        }

        if ($currentRows->isNotEmpty()) {
            ExerciseProgramExercise::query()
                ->where('exercise_program_id', $this->exerciseProgram->id)
                ->whereIn('id', $currentRows->keys()->all())
                ->delete();
        }

        $pivotIdMap = [];

        foreach ($sourceRows as $index => $exercise) {
            $newPivot = ExerciseProgramExercise::create([
                'exercise_program_id' => $this->exerciseProgram->id,
                'exercise_id' => $exercise->id,
                'sort' => $exercise->pivot->sort ?? $index,
                'group' => $exercise->pivot->group,
                'type' => $this->activeSection,
            ]);

            $pivotIdMap[(int) $exercise->pivot->id] = (int) $newPivot->id;
        }

        $sourceConfig = $sourceProgram->config;

        foreach ($pivotIdMap as $sourcePivotId => $targetPivotId) {
            if (isset($sourceConfig->exercises[$sourcePivotId])) {
                $config->setDefaultExerciseOverrides(
                    $targetPivotId,
                    ExerciseOverrides::from($sourceConfig->defaultExerciseOverrides($sourcePivotId)->toArray())
                );
            }
        }

        foreach ($sourceConfig->userExercises as $userId => $overridesByExercise) {
            foreach ($overridesByExercise as $sourcePivotId => $overrides) {
                $targetPivotId = $pivotIdMap[(int) $sourcePivotId] ?? null;

                if ($targetPivotId === null) {
                    continue;
                }

                $config->setUserExerciseOverrides(
                    (int) $userId,
                    $targetPivotId,
                    ExerciseOverrides::from(
                        ($overrides instanceof ExerciseOverrides ? $overrides : ExerciseOverrides::from($overrides))->toArray()
                    )
                );
            }
        }

        $this->exerciseProgram->config = $config;
        $this->exerciseProgram->save();
        $this->exerciseProgram->refresh();
        $this->loadExerciseData();
        unset($this->fieldsets, $this->exercises, $this->exerciseGroupLabels);
        app(TrainingSessionRebuildService::class)->rebuildFutureSlotsForExerciseProgram($this->exerciseProgram->id);
        Flux::modal($this->importConfirmModalName())->close();

        Flux::toast(
            text: __('Imported :name into :section', [
                'name' => $sourceProgram->name,
                'section' => $this->sectionLabel($this->activeSection),
            ]),
            variant: 'success',
        );
    }

    public function saveSectionExercises(): void
    {
        $currentRows = $this->exerciseProgram->exercises()
            ->wherePivot('type', $this->activeSection)
            ->get()
            ->keyBy(fn (Exercise $exercise) => (int) $exercise->pivot->id);

        $newRows = collect($this->data['section_exercises'] ?? []);
        $currentIds = $currentRows->keys()->all();
        $newIds = $newRows
            ->pluck('program_exercise_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $programExerciseIdsToRemove = array_diff($currentIds, $newIds);

        if ($programExerciseIdsToRemove !== []) {
            ExerciseProgramExercise::query()
                ->where('exercise_program_id', $this->exerciseProgram->id)
                ->whereIn('id', $programExerciseIdsToRemove)
                ->delete();
        }

        $config = $this->exerciseProgram->config;
        $configChanged = false;

        foreach ($programExerciseIdsToRemove as $programExerciseId) {
            $config->removeExerciseOverrides((int) $programExerciseId);
            $configChanged = true;
        }

        foreach ($newRows->values() as $index => $exerciseData) {
            $exerciseId = isset($exerciseData['id']) ? (int) $exerciseData['id'] : null;
            if ($exerciseId === null || $exerciseId === 0) {
                continue;
            }

            $programExerciseId = isset($exerciseData['program_exercise_id']) ? (int) $exerciseData['program_exercise_id'] : null;
            $sort = $exerciseData['sort'] ?? $index;
            $group = ! empty($exerciseData['group']) ? $exerciseData['group'] : null;

            if ($programExerciseId === null || ! $currentRows->has($programExerciseId)) {
                $newPivot = ExerciseProgramExercise::create([
                    'exercise_program_id' => $this->exerciseProgram->id,
                    'exercise_id' => $exerciseId,
                    'sort' => $sort,
                    'group' => $group,
                    'type' => $this->activeSection,
                ]);

                $this->setDefaultOverridesForExercise($config, $exerciseId, $newPivot->id);
                $configChanged = true;

                continue;
            }

            $currentExercise = $currentRows->get($programExerciseId);
            $exerciseChanged = (int) $currentExercise->id !== $exerciseId;

            ExerciseProgramExercise::query()
                ->where('id', $programExerciseId)
                ->update([
                    'exercise_id' => $exerciseId,
                    'sort' => $sort,
                    'group' => $group,
                    'type' => $this->activeSection,
                ]);

            if ($exerciseChanged) {
                $this->setDefaultOverridesForExercise($config, $exerciseId, $programExerciseId);
                $configChanged = true;
            }
        }

        if ($configChanged) {
            $this->exerciseProgram->config = $config;
            $this->exerciseProgram->save();
        }

        $this->exerciseProgram->refresh();
        unset($this->fieldsets, $this->exercises, $this->exerciseGroupLabels);
        $this->loadExerciseData();
        app(TrainingSessionRebuildService::class)->rebuildFutureSlotsForExerciseProgram($this->exerciseProgram->id);
    }

    protected function setDefaultOverridesForExercise(ExercisePlanConfig $config, int $exerciseId, int $programExerciseId): void
    {
        $exercise = Exercise::find($exerciseId);

        if (! $exercise) {
            return;
        }

        $configArray = json_decode($exercise->getRawOriginal('config') ?? '{}', true) ?: [];
        $config->setDefaultExerciseOverrides($programExerciseId, $this->buildExerciseOverrides($configArray));
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
    public function showAthleteContext(): bool
    {
        return ($this->hasAutoWeightExercises && $this->planHasBlock) || $this->hasHeartRateExercises;
    }

    #[On('exercise-overrides-changed')]
    public function onExerciseOverridesChanged(): void
    {
        $this->exerciseProgram->refresh();
    }

    public function sectionLabel(string $section): string
    {
        return match ($section) {
            'warm_up' => __('Warm Up'),
            'warm_down' => __('Warm Down'),
            default => __('Main'),
        };
    }

    protected function importProgramType(): ExerciseProgramTypeEnum
    {
        if ($this->programType === ExerciseProgramTypeEnum::WarmUp) {
            return ExerciseProgramTypeEnum::WarmUp;
        }

        if ($this->programType === ExerciseProgramTypeEnum::WarmDown) {
            return ExerciseProgramTypeEnum::WarmDown;
        }

        return match ($this->activeSection) {
            'warm_up' => ExerciseProgramTypeEnum::WarmUp,
            'warm_down' => ExerciseProgramTypeEnum::WarmDown,
            default => ExerciseProgramTypeEnum::Program,
        };
    }

    protected function importSourceSection(ExerciseProgram $sourceProgram): string
    {
        return $sourceProgram->type === ExerciseProgramTypeEnum::Program
            ? $this->activeSection
            : 'main';
    }

    public function importConfirmModalName(): string
    {
        return 'confirm-import-program-exercises-'.$this->exerciseProgram->id.'-'.$this->planId;
    }

    public function render()
    {
        if (! in_array($this->activeSection, $this->availableSections, true)) {
            $this->activeSection = $this->availableSections[0] ?? 'main';
            $this->syncSectionFormData();
        }

        return view('livewire.training.view.program-editor');
    }
}
