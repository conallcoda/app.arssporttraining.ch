<?php

namespace App\Livewire\Training\View;

use App\Data\Training\Config\ExerciseOverrides;
use App\Data\Training\Config\ExercisePlanConfig;
use App\Data\Exercise\Preview\SessionGroupingConfig;
use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Form\Fields\Exercise\Exercises;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Exercise\ExerciseProgramTypeEnum;
use App\Training\ExerciseGroupLabeler;
use App\Training\TrainingSessionRebuildDispatcher;
use Coda\Cms\Livewire\Concerns\InteractsWithFormData;
use Coda\FormKit\Form;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Spatie\LaravelData\Optional;

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

    public array $weekSessionDates = [];

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

    public array $relationshipSearch = [];

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
        array $weekSessionDates = [],
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
        $this->weekSessionDates = $weekSessionDates;
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

        $this->data['session_grouping'] = $this->resolvedSessionGrouping()->toArray();

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
                Exercises::make('section_exercises')->withOptions()->withSearch()->withOptionView()->groupable(),
            ]);
    }

    #[Computed]
    public function fields(): array
    {
        return $this->formConfig->getFields();
    }

    #[Computed]
    public function sessionGroupingFormConfig(): Form
    {
        return Form::make()
            ->fieldset('Session Grouping', SessionGroupingConfig::fields($this->data['session_grouping'] ?? []), prefix: 'data.session_grouping');
    }

    #[Computed]
    public function sessionGroupingFieldset(): mixed
    {
        return $this->sessionGroupingFormConfig->resolveFieldsets($this->data)[0] ?? null;
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

        if ($property === 'data.session_grouping.mode') {
            $mode = (string) ($this->data['session_grouping']['mode'] ?? null);
            $this->data['session_grouping']['groupSize'] = SessionGroupingMode::defaultGroupSize($mode);
        }

        if (str_starts_with($property, 'data.session_grouping.')) {
            $this->saveSessionGrouping();
        }

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
        $this->exerciseProgram->saveQuietly();
        app(TrainingSessionRebuildDispatcher::class)
            ->dispatchFutureSlotsForExerciseProgramChange($this->exerciseProgram->id);
    }

    protected function resolvedSessionGrouping(): SessionGroupingConfig
    {
        $stored = $this->exerciseProgram->config->resolvedSessionGrouping();

        if ($stored !== null) {
            return $stored;
        }

        return $this->coachDefaultSessionGrouping();
    }

    protected function saveSessionGrouping(): void
    {
        $sessionGrouping = SessionGroupingConfig::from($this->data['session_grouping'] ?? []);
        $this->data['session_grouping'] = $sessionGrouping->toArray();
        $coachDefault = $this->coachDefaultSessionGrouping();

        $config = $this->exerciseProgram->config;
        $config->sessionGrouping = $sessionGrouping->toArray() === $coachDefault->toArray()
            ? Optional::create()
            : $sessionGrouping;
        $this->exerciseProgram->config = $config;
        $this->exerciseProgram->saveQuietly();

        app(TrainingSessionRebuildDispatcher::class)
            ->dispatchFutureSlotsForExerciseProgramChange($this->exerciseProgram->id);
    }

    protected function coachDefaultSessionGrouping(): SessionGroupingConfig
    {
        $user = Auth::user();

        return SessionGroupingConfig::from([
            'mode' => $mode = (string) ($user?->config->get('settings.session_grouping.mode', SessionGroupingMode::defaultMode()) ?? SessionGroupingMode::defaultMode()),
            'groupSize' => SessionGroupingMode::normalizeGroupSize(
                is_numeric($user?->config->get('settings.session_grouping.groupSize'))
                    ? (int) $user?->config->get('settings.session_grouping.groupSize')
                    : null,
                $mode,
            ),
            'copyValuesAutomatically' => (bool) ($user?->config->get('settings.session_grouping.copyValuesAutomatically', SessionGroupingMode::defaultCopyValuesAutomatically()) ?? SessionGroupingMode::defaultCopyValuesAutomatically()),
        ]);
    }

    public function removeRelationshipItem(string $fieldName, int $index): void
    {
        if (! isset($this->data[$fieldName][$index])) {
            return;
        }

        unset($this->data[$fieldName][$index]);
        $this->data[$fieldName] = array_values($this->data[$fieldName]);
        unset($this->relationshipSearch[$fieldName]);

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

    public function reorderRelationshipItem(string $fieldName, int $sourceIndex, int $targetIndex): void
    {
        if (! isset($this->data[$fieldName])) {
            return;
        }

        $items = $this->data[$fieldName];

        if ($sourceIndex < 0 || $sourceIndex >= count($items) || $targetIndex < 0 || $targetIndex >= count($items)) {
            return;
        }

        $moved = array_splice($items, $sourceIndex, 1);
        array_splice($items, $targetIndex, 0, $moved);

        $field = collect($this->getAllFields())->firstWhere('name', $fieldName);
        if ($field?->sortable) {
            foreach ($items as $i => $item) {
                $items[$i]['sort'] = $i;
            }
        }

        $this->data[$fieldName] = $items;

        if ($fieldName !== 'section_exercises') {
            return;
        }

        $this->data[$this->sectionFieldName($this->activeSection)] = $items;
        $this->saveSectionExercises();
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

        $didChange = false;

        DB::transaction(function () use ($sourceProgram, $sourceRows, &$didChange): void {
            $config = $this->exerciseProgram->config;
            $currentRows = $this->exerciseProgram->exercises()
                ->wherePivot('type', $this->activeSection)
                ->get()
                ->keyBy(fn (Exercise $exercise) => (int) $exercise->pivot->id);

            foreach ($currentRows->keys() as $programExerciseId) {
                $config->removeExerciseOverrides((int) $programExerciseId);
                $config->removeExerciseOverridesForAllUsers((int) $programExerciseId);
            }

            $pivotIdMap = [];

            ExerciseProgramExercise::withoutEvents(function () use ($currentRows, $sourceRows, &$pivotIdMap, &$didChange): void {
                if ($currentRows->isNotEmpty()) {
                    ExerciseProgramExercise::query()
                        ->where('exercise_program_id', $this->exerciseProgram->id)
                        ->whereIn('id', $currentRows->keys()->all())
                        ->delete();
                    $didChange = true;
                }

                foreach ($sourceRows as $index => $exercise) {
                    $newPivot = ExerciseProgramExercise::create([
                        'exercise_program_id' => $this->exerciseProgram->id,
                        'exercise_id' => $exercise->id,
                        'sort' => $exercise->pivot->sort ?? $index,
                        'group' => $exercise->pivot->group,
                        'type' => $this->activeSection,
                    ]);

                    $pivotIdMap[(int) $exercise->pivot->id] = (int) $newPivot->id;
                    $didChange = true;
                }
            });

            $sourceConfig = $sourceProgram->config;
            $config->copyMappedExerciseOverridesFrom($sourceConfig, $pivotIdMap);

            $this->exerciseProgram->config = $config;
            $this->exerciseProgram->saveQuietly();
        });

        if ($didChange) {
            $this->dispatchSharedProgramRebuild();
        }

        $this->exerciseProgram->refresh();
        $this->loadExerciseData();
        unset($this->fieldsets, $this->exercises, $this->exerciseGroupLabels);
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

        $config = $this->exerciseProgram->config;
        $configChanged = false;
        $didChange = false;

        foreach ($programExerciseIdsToRemove as $programExerciseId) {
            $config->removeExerciseOverrides((int) $programExerciseId);
            $configChanged = true;
        }

        DB::transaction(function () use (
            $programExerciseIdsToRemove,
            $newRows,
            $currentRows,
            &$config,
            &$configChanged,
            &$didChange,
        ): void {
            ExerciseProgramExercise::withoutEvents(function () use (
                $programExerciseIdsToRemove,
                $newRows,
                $currentRows,
                &$config,
                &$configChanged,
                &$didChange,
            ): void {
                if ($programExerciseIdsToRemove !== []) {
                    ExerciseProgramExercise::query()
                        ->where('exercise_program_id', $this->exerciseProgram->id)
                        ->whereIn('id', $programExerciseIdsToRemove)
                        ->delete();
                    $didChange = true;
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
                        $didChange = true;

                        continue;
                    }

                    $currentExercise = $currentRows->get($programExerciseId);
                    $exerciseChanged = (int) $currentExercise->id !== $exerciseId;
                    $sortChanged = (int) ($currentExercise->pivot->sort ?? 0) !== (int) $sort;
                    $groupChanged = ($currentExercise->pivot->group ?? null) !== $group;
                    $typeChanged = ($currentExercise->pivot->type ?? 'main') !== $this->activeSection;

                    if (! $exerciseChanged && ! $sortChanged && ! $groupChanged && ! $typeChanged) {
                        continue;
                    }

                    ExerciseProgramExercise::query()
                        ->where('id', $programExerciseId)
                        ->update([
                            'exercise_id' => $exerciseId,
                            'sort' => $sort,
                            'group' => $group,
                            'type' => $this->activeSection,
                        ]);

                    $didChange = true;

                    if ($exerciseChanged) {
                        $this->setDefaultOverridesForExercise($config, $exerciseId, $programExerciseId);
                        $configChanged = true;
                    }
                }
            });

            if ($configChanged) {
                $this->exerciseProgram->config = $config;
                $this->exerciseProgram->saveQuietly();
            }
        });

        if ($didChange) {
            $this->dispatchSharedProgramRebuild();
        }

        $this->exerciseProgram->refresh();
        unset($this->fieldsets, $this->exercises, $this->exerciseGroupLabels);
        $this->loadExerciseData();
    }

    protected function setDefaultOverridesForExercise(ExercisePlanConfig $config, int $exerciseId, int $programExerciseId): void
    {
        $config->setDefaultExerciseOverrides(
            $programExerciseId,
            new ExerciseOverrides,
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

    protected function dispatchSharedProgramRebuild(): void
    {
        app(TrainingSessionRebuildDispatcher::class)
            ->dispatchFutureSlotsForExerciseProgramChange($this->exerciseProgram->id);
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
