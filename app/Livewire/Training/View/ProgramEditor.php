<?php

namespace App\Livewire\Training\View;

use App\Data\Training\Config\ExerciseOverrides;
use App\Data\Training\Config\ExercisePlanConfig;
use App\Form\Fields\Exercise\Exercises;
use App\Livewire\Concerns\InteractsWithExerciseSelectorPrograms;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Exercise\ExerciseProgramTypeEnum;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Users\User;
use App\Support\Profiling\PlanGridProfiler;
use App\Support\Training\ExerciseProgramSelectorPreviewService;
use App\Support\Training\ProgramExerciseOrder;
use App\Support\Training\WeekSessionCountResolver;
use App\Training\ExerciseGroupLabeler;
use App\Training\ExerciseProgramSectionMutationService;
use App\Training\TrainingSessionRebuildDispatcher;
use Coda\Cms\Livewire\Concerns\InteractsWithFormData;
use Coda\FormKit\Form;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class ProgramEditor extends Component
{
    use InteractsWithExerciseSelectorPrograms;
    use InteractsWithFormData {
        InteractsWithFormData::updated as traitUpdated;
        InteractsWithFormData::removeRelationshipSelectorItem as traitRemoveRelationshipSelectorItem;
        InteractsWithFormData::toggleRelationshipSelectorItem as traitToggleRelationshipSelectorItem;
        InteractsWithFormData::applyRelationshipSelectorDraft as traitApplyRelationshipSelectorDraft;
        InteractsWithFormData::applyRelationshipSelectorClientState as traitApplyRelationshipSelectorClientState;
    }

    private const SECTION_TYPES = ['main', 'warm_up', 'warm_down'];

    public ExerciseProgram $exerciseProgram;

    public int|string $weeks = 5;

    public bool $showWeeksInput = false;

    public int $sessionsPerWeek = 1;

    public array $weekLabels = [];

    public array $weekSessions = [];

    public array $weekSessionDates = [];

    public array $expandedWeeks = [];

    public array $lockedSessionsByWeek = [];

    public bool $sessionLabels = false;

    public bool $showNameInput = false;

    public bool $showActualValueTabs = false;

    public string $valueDisplayMode = 'planned';

    public int $gridRenderVersion = 0;

    public string $gridLayout = 'side-by-side';

    public int $planId;

    #[Reactive]
    public ?int $userId = null;

    public ?int $scheduledTrainingProgramId = null;

    #[Reactive]
    public ?int $planMeasuredReps = null;

    #[Reactive]
    public ?float $planMeasuredWeight = null;

    #[Reactive]
    public int|float|null $planTargetGoal = 10;

    #[Reactive]
    public ?int $planMaxHR = null;

    #[Reactive]
    public ?int $planIatPercent = null;

    #[Reactive]
    public ?string $planBlockGoalLabel = null;

    #[Reactive]
    public ?string $plan1rmLabel = null;

    #[Reactive]
    public ?string $planHeartRateLabel = null;

    public bool $hasAutoWeightExercises = false;

    public bool $hasHeartRateExercises = false;

    public bool $planHasBlock = false;

    #[Reactive]
    public array $planGroupMemberMetrics = [];

    public array $data = [];

    public array $relationshipSearch = [];

    public string $activeSection = 'main';

    public ?int $importProgramId = null;

    public ?int $previewTrainingProgramId = null;

    public ?string $previewInitialSessionKey = null;

    public ?string $previewInitialSection = null;

    public ?int $previewInitialExerciseId = null;

    public ?int $previewInitialExerciseSort = null;

    public int $previewOpenVersion = 0;

    public function openPreviewModal(): void
    {
        if ($this->userId === null || $this->scheduledTrainingProgramId === null) {
            return;
        }

        $this->previewInitialSessionKey = null;
        $this->previewInitialSection = null;
        $this->previewInitialExerciseId = null;
        $this->previewInitialExerciseSort = null;
        $this->previewOpenVersion++;

        $this->previewTrainingProgramId = $this->scheduledTrainingProgramId;

        Flux::modal($this->previewModalName())->show();
    }

    #[On('open-program-preview-at-session')]
    public function openPreviewAtSession(string $sessionKey, string $section, int $exerciseId, int $exerciseSort): void
    {
        if ($this->userId === null || $this->scheduledTrainingProgramId === null) {
            return;
        }

        $this->previewInitialSessionKey = $sessionKey;
        $this->previewInitialSection = $section;
        $this->previewInitialExerciseId = $exerciseId;
        $this->previewInitialExerciseSort = $exerciseSort;
        $this->previewOpenVersion++;

        $this->previewTrainingProgramId = $this->scheduledTrainingProgramId;

        Flux::modal($this->previewModalName())->show();
    }

    public function mount(
        ExerciseProgram $exerciseProgram,
        int $planId,
        ?int $scheduledTrainingProgramId = null,
        int $weeks = 5,
        bool $showWeeksInput = false,
        int $sessionsPerWeek = 1,
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
        bool $showActualValueTabs = false,
        ?string $planBlockGoalLabel = null,
        ?string $plan1rmLabel = null,
        ?string $planHeartRateLabel = null,
        bool $hasAutoWeightExercises = false,
        bool $hasHeartRateExercises = false,
        bool $planHasBlock = false,
        array $planGroupMemberMetrics = [],
    ): void {
        $span = PlanGridProfiler::start('ProgramEditor.mount', [
            'component' => static::class,
            'exercise_program_id' => $exerciseProgram->id,
            'plan_id' => $planId,
            'scheduled_training_program_id' => $scheduledTrainingProgramId,
            'user_id' => $userId,
            'weeks' => $weeks,
            'sessions_per_week' => $sessionsPerWeek,
            'week_session_dates_count' => collect($weekSessionDates)->flatten()->count(),
        ]);

        try {
            $this->exerciseProgram = $exerciseProgram;
            if ($exerciseProgram->type !== ExerciseProgramTypeEnum::Program) {
                $this->activeSection = 'main';
            }
            $this->planId = $planId;
            $this->scheduledTrainingProgramId = $scheduledTrainingProgramId;
            $this->showWeeksInput = $showWeeksInput;
            $this->weeks = $showWeeksInput ? $exerciseProgram->config->weeks : $weeks;
            $this->sessionsPerWeek = $sessionsPerWeek;
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
            $this->showActualValueTabs = $showActualValueTabs;
            $this->valueDisplayMode = 'planned';
            $this->gridRenderVersion = 0;
            $this->planBlockGoalLabel = $planBlockGoalLabel;
            $this->plan1rmLabel = $plan1rmLabel;
            $this->planHeartRateLabel = $planHeartRateLabel;
            $this->hasAutoWeightExercises = $hasAutoWeightExercises;
            $this->hasHeartRateExercises = $hasHeartRateExercises;
            $this->planHasBlock = $planHasBlock;
            $this->planGroupMemberMetrics = $planGroupMemberMetrics;
            $this->loadExerciseData();
        } finally {
            PlanGridProfiler::end($span, $this->profileContext());
        }
    }

    public function updatedValueDisplayMode(): void
    {
        $this->gridRenderVersion++;
    }

    protected function loadExerciseData(): void
    {
        PlanGridProfiler::measure('ProgramEditor.loadExerciseData', $this->profileContext(), function (): void {
            $this->exerciseProgram->unsetRelation('exercises');
            $this->exerciseProgram->load([
                'exercises' => fn ($q) => $q->orderByPivot('type')->orderByPivot('sort')->orderByPivot('id'),
                'exercises.equipment',
                'exercises.modifiers',
            ]);

            foreach (self::SECTION_TYPES as $type) {
                $this->data[$this->sectionFieldName($type)] = $this->serializeSectionExercises($type);
            }

            $this->syncSectionFormData();
        });
    }

    protected function serializeSectionExercises(string $type): array
    {
        $rows = app(ProgramExerciseOrder::class)
            ->sortProgramExercises(
                $this->exerciseProgram->exercises
                    ->filter(fn (Exercise $exercise) => ($exercise->pivot->type ?? 'main') === $type)
                    ->values(),
                includeType: false,
            )
            ->values()
            ->map(fn (Exercise $exercise) => [
                'id' => $exercise->id,
                'program_exercise_id' => $exercise->pivot->id,
                '_key' => uniqid('item_', true),
                'sort' => $exercise->pivot->sort ?? 0,
                'group' => $exercise->pivot->group,
            ])
            ->all();

        return app(ProgramExerciseOrder::class)->normalizeRows($rows);
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
                Exercises::make('section_exercises')->withOptions()->withSearch()->withOptionView(),
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
    public function planConfigArray(): array
    {
        return PlanGridProfiler::measure('ProgramEditor.planConfigArray', $this->profileContext(), function (): array {
            return $this->exerciseProgram->config->toArray();
        });
    }

    #[Computed]
    public function exercises(): Collection
    {
        return PlanGridProfiler::measure('ProgramEditor.exercises', $this->profileContext([
            'active_section' => $this->activeSection,
        ]), function (): Collection {
            return app(ProgramExerciseOrder::class)
                ->sortProgramExercises(
                    $this->exerciseProgram->exercises
                        ->filter(fn (Exercise $exercise) => ($exercise->pivot->type ?? 'main') === $this->activeSection)
                        ->values(),
                    includeType: false,
                );
        });
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

    /** @return array<int, array<int, array{label: string, color: string}>> */
    #[Computed]
    public function exerciseBadgesByPivotId(): array
    {
        return PlanGridProfiler::measure('ProgramEditor.exerciseBadgesByPivotId', $this->profileContext(), function (): array {
            return $this->exercises
                ->mapWithKeys(fn (Exercise $exercise): array => [
                    (int) $exercise->pivot->id => $this->buildExerciseBadges($exercise),
                ])
                ->all();
        });
    }

    /** @return array<int, array{label: string, color: string}> */
    protected function buildExerciseBadges(Exercise $exercise): array
    {
        $badges = [];

        foreach ($exercise->equipment as $tag) {
            $badges[] = ['label' => $tag->name, 'color' => 'blue'];
        }

        foreach ($exercise->modifiers as $tag) {
            $badges[] = ['label' => $tag->name, 'color' => ''];
        }

        return $badges;
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

    public function updatedWeeks(mixed $value): void
    {
        if (! $this->showWeeksInput) {
            return;
        }

        if (! is_numeric($value) || (int) $value < 1 || (int) $value > 52) {
            $this->weeks = max(1, min(52, (int) ($this->exerciseProgram->config->weeks ?: 5)));
            $this->addError('weeks', __('Preview weeks must be between 1 and 52.'));

            return;
        }

        $this->resetValidation('weeks');
        $this->weeks = (int) $value;

        $config = $this->exerciseProgram->config;
        $config->weeks = $this->weeks;
        $config->pruneCurrentGridOverridesToSessionCounts(
            WeekSessionCountResolver::resolveForWeeks(
                weeks: $this->weeks,
                fallbackSessionsPerWeek: $this->sessionsPerWeek,
                weekSessions: $this->weekSessions,
                weekSessionDates: $this->weekSessionDates,
                lockedSessionsByWeek: $this->lockedSessionsByWeek,
            ),
        );
        $this->exerciseProgram->config = $config;
        $this->exerciseProgram->saveQuietly();
        unset($this->planConfigArray);
        app(TrainingSessionRebuildDispatcher::class)
            ->dispatchFutureSlotsForExerciseProgramChange($this->exerciseProgram->id);
    }

    public function removeRelationshipItem(string $fieldName, int $index): void
    {
        if (! isset($this->data[$fieldName][$index])) {
            return;
        }

        unset($this->data[$fieldName][$index]);
        $this->data[$fieldName] = app(ProgramExerciseOrder::class)->normalizeRows(array_values($this->data[$fieldName]));
        unset($this->relationshipSearch[$fieldName]);

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

        $this->data[$fieldName] = app(ProgramExerciseOrder::class)->moveRow($this->data[$fieldName], $index, $direction);

        if ($fieldName === 'section_exercises') {
            $this->data[$this->sectionFieldName($this->activeSection)] = $this->data[$fieldName];
            $this->saveSectionExercises();
        }
    }

    public function toggleRelationshipSelectorItem(string $fieldName, mixed $value): void
    {
        $this->traitToggleRelationshipSelectorItem($fieldName, $value);

        if ($fieldName !== 'section_exercises') {
            return;
        }

        $this->data[$this->sectionFieldName($this->activeSection)] = $this->data[$fieldName] ?? [];
        $this->saveSectionExercises();
    }

    public function applyRelationshipSelectorDraft(string $fieldName, array $orderedKeys = []): void
    {
        $this->traitApplyRelationshipSelectorDraft($fieldName, $orderedKeys);

        if ($fieldName !== 'section_exercises') {
            return;
        }

        $this->data[$fieldName] = app(ProgramExerciseOrder::class)->normalizeRows($this->data[$fieldName] ?? []);
        $this->data[$this->sectionFieldName($this->activeSection)] = $this->data[$fieldName] ?? [];
        $this->saveSectionExercises();
        unset($this->fieldsets, $this->exercises, $this->exerciseGroupLabels);
    }

    public function applyRelationshipSelectorClientState(string $fieldName, array $items = []): void
    {
        $this->traitApplyRelationshipSelectorClientState($fieldName, $items);

        if ($fieldName !== 'section_exercises') {
            return;
        }

        $this->data[$fieldName] = app(ProgramExerciseOrder::class)->normalizeRows($this->data[$fieldName] ?? []);
        $this->data[$this->sectionFieldName($this->activeSection)] = $this->data[$fieldName] ?? [];
        $this->saveSectionExercises();
        unset($this->fieldsets, $this->exercises, $this->exerciseGroupLabels);
    }

    public function removeRelationshipSelectorItem(string $fieldName, int $index): void
    {
        $this->traitRemoveRelationshipSelectorItem($fieldName, $index);

        if ($fieldName !== 'section_exercises') {
            return;
        }

        $this->data[$fieldName] = app(ProgramExerciseOrder::class)->normalizeRows($this->data[$fieldName] ?? []);
        $this->data[$this->sectionFieldName($this->activeSection)] = $this->data[$fieldName] ?? [];
        $this->saveSectionExercises();
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
        $this->data[$fieldName] = app(ProgramExerciseOrder::class)->normalizeRows($items);

        if ($fieldName !== 'section_exercises') {
            return;
        }

        $this->data[$this->sectionFieldName($this->activeSection)] = $this->data[$fieldName];
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
        $sourceRows = app(ProgramExerciseOrder::class)
            ->sortProgramExercises(
                $sourceProgram->exercises
                    ->filter(fn (Exercise $exercise) => ($exercise->pivot->type ?? 'main') === $sourceSection)
                    ->values(),
                includeType: false,
            )
            ->values()
            ->map(fn (Exercise $exercise): array => [
                'id' => $exercise->id,
                '_key' => uniqid('item_', true),
                'sort' => $exercise->pivot->sort ?? 0,
                'group' => $exercise->pivot->group,
                'source_program_id' => $sourceProgram->id,
                'source_program_exercise_id' => (int) ($exercise->pivot->id ?? 0),
            ])
            ->all();

        $sourceRows = app(ProgramExerciseOrder::class)->normalizeRows($sourceRows);
        $this->data['section_exercises'] = $sourceRows;
        $this->data[$this->sectionFieldName($this->activeSection)] = $sourceRows;
        $result = $this->saveSectionExercises();
        Flux::modal($this->importConfirmModalName())->close();

        Flux::toast(
            text: __('Imported :name into :section', [
                'name' => $sourceProgram->name,
                'section' => $this->sectionLabel($this->activeSection),
            ]),
            variant: 'success',
        );
    }

    /**
     * @return array{preservedImmutableCount:int}
     */
    public function saveSectionExercises(): array
    {
        $currentRows = $this->exerciseProgram->exercises()
            ->wherePivot('type', $this->activeSection)
            ->get()
            ->keyBy(fn (Exercise $exercise) => (int) $exercise->pivot->id);

        $normalization = app(ExerciseProgramSectionMutationService::class)->normalize(
            currentRows: $currentRows,
            proposedRows: collect($this->data['section_exercises'] ?? []),
            config: $this->exerciseProgram->config,
            lockedSessionsByWeek: $this->lockedSessionsByWeek,
            weekSessionDates: $this->weekSessionDates,
        );

        $this->data['section_exercises'] = $normalization['rows'];
        $this->data[$this->sectionFieldName($this->activeSection)] = $normalization['rows'];

        $newRows = collect($normalization['rows']);
        $currentIds = $currentRows->keys()->all();
        $newIds = $newRows
            ->pluck('program_exercise_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $programExerciseIdsToRemove = array_diff($currentIds, $newIds);
        $referencedProgramExerciseIdsToKeep = $this->referencedProgramExerciseIds($programExerciseIdsToRemove);
        $programExerciseIdsToDelete = array_values(array_diff($programExerciseIdsToRemove, $referencedProgramExerciseIdsToKeep));

        $config = $this->exerciseProgram->config;
        $configChanged = false;
        $didChange = false;
        $sourcePivotMaps = [];

        foreach ($programExerciseIdsToDelete as $programExerciseId) {
            $config->removeExerciseOverrides((int) $programExerciseId);
            $config->removeExerciseOverridesForAllUsers((int) $programExerciseId);
            $configChanged = true;
        }

        DB::transaction(function () use (
            $programExerciseIdsToDelete,
            $newRows,
            $currentRows,
            $normalization,
            &$config,
            &$configChanged,
            &$didChange,
            &$sourcePivotMaps,
        ): void {
            ExerciseProgramExercise::withoutEvents(function () use (
                $programExerciseIdsToDelete,
                $newRows,
                $currentRows,
                $normalization,
                &$config,
                &$configChanged,
                &$didChange,
                &$sourcePivotMaps,
            ): void {
                if ($programExerciseIdsToDelete !== []) {
                    ExerciseProgramExercise::query()
                        ->where('exercise_program_id', $this->exerciseProgram->id)
                        ->whereIn('id', $programExerciseIdsToDelete)
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

                        $startsAtDate = $normalization['startsAtDate'] ?? null;
                        $this->setDefaultOverridesForExercise($config, $exerciseId, $newPivot->id, $startsAtDate);

                        $sourceProgramId = isset($exerciseData['source_program_id']) ? (int) $exerciseData['source_program_id'] : 0;
                        $sourcePivotId = isset($exerciseData['source_program_exercise_id']) ? (int) $exerciseData['source_program_exercise_id'] : 0;

                        if ($sourceProgramId > 0 && $sourcePivotId > 0) {
                            $sourcePivotMaps[$sourceProgramId][$sourcePivotId] = (int) $newPivot->id;
                        }

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

            foreach ($sourcePivotMaps as $sourceProgramId => $pivotIdMap) {
                $sourceProgram = ExerciseProgram::query()->find($sourceProgramId);

                if (! $sourceProgram || $pivotIdMap === []) {
                    continue;
                }

                $config->copyMappedExerciseOverridesFrom(
                    $sourceProgram->config,
                    $pivotIdMap,
                    $normalization['startsAtDate'] ?? null,
                );
                $configChanged = true;
            }

            if ($configChanged) {
                $this->exerciseProgram->config = $config;
                $this->exerciseProgram->saveQuietly();
                unset($this->planConfigArray);
            }
        });

        if ($didChange) {
            app(ExerciseProgramSelectorPreviewService::class)->syncProgram($this->exerciseProgram->id);
            $this->dispatchSharedProgramRebuild();
        }

        $this->exerciseProgram->refresh();
        unset($this->fieldsets, $this->exercises, $this->exerciseGroupLabels);
        $this->loadExerciseData();

        $preservedMaterializedCount = count($referencedProgramExerciseIdsToKeep);

        if (($normalization['preservedImmutableCount'] ?? 0) > 0 || $preservedMaterializedCount > 0) {
            Flux::toast(
                text: __('Some historical exercises were kept because recorded past sessions can no longer be changed.'),
                variant: 'warning',
            );
        }

        return [
            'preservedImmutableCount' => (int) ($normalization['preservedImmutableCount'] ?? 0),
            'preservedMaterializedCount' => $preservedMaterializedCount,
        ];
    }

    /**
     * @param  array<int, int>  $programExerciseIds
     * @return array<int, int>
     */
    protected function referencedProgramExerciseIds(array $programExerciseIds): array
    {
        if ($programExerciseIds === []) {
            return [];
        }

        return TrainingProgramSlotExercise::query()
            ->whereIn('exercise_program_exercise_id', array_map('intval', $programExerciseIds))
            ->distinct()
            ->pluck('exercise_program_exercise_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    protected function setDefaultOverridesForExercise(ExercisePlanConfig $config, int $exerciseId, int $programExerciseId, ?string $startsAtDate = null): void
    {
        $config->setDefaultExerciseOverrides(
            $programExerciseId,
            new ExerciseOverrides(startsAtDate: $startsAtDate),
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
        unset($this->planConfigArray, $this->exercises, $this->exerciseGroupLabels, $this->exerciseBadgesByPivotId);
        $this->gridRenderVersion++;
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
            'warm_down' => __('Cool Down'),
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
        return 'main';
    }

    protected function exerciseSelectorImportProgramType(string $fieldName): ExerciseProgramTypeEnum
    {
        return $fieldName === 'section_exercises'
            ? $this->importProgramType()
            : ExerciseProgramTypeEnum::Program;
    }

    /**
     * @return array<int, string>
     */
    protected function exerciseSelectorImportProgramTypes(string $fieldName): array
    {
        if ($fieldName !== 'section_exercises') {
            return parent::exerciseSelectorImportProgramTypes($fieldName);
        }

        return [
            ExerciseProgramTypeEnum::Program->value,
            ExerciseProgramTypeEnum::WarmUp->value,
            ExerciseProgramTypeEnum::WarmDown->value,
        ];
    }

    protected function exerciseSelectorCurrentProgramId(string $fieldName): ?int
    {
        return $fieldName === 'section_exercises'
            ? $this->exerciseProgram->id
            : (is_numeric(data_get($this, 'data.id')) ? (int) data_get($this, 'data.id') : null);
    }

    protected function exerciseSelectorTargetSection(string $fieldName): string
    {
        return $fieldName === 'section_exercises'
            ? $this->activeSection
            : 'main';
    }

    protected function exerciseSelectorSourceSection(ExerciseProgram $sourceProgram, string $fieldName): string
    {
        return $fieldName === 'section_exercises'
            ? $this->importSourceSection($sourceProgram)
            : 'main';
    }

    public function importConfirmModalName(): string
    {
        return 'confirm-import-program-exercises-'.$this->exerciseProgram->id.'-'.$this->planId;
    }

    public function previewModalName(): string
    {
        return 'program-preview-'.$this->exerciseProgram->id.'-'.$this->planId;
    }

    #[Computed]
    public function previewAthlete(): ?User
    {
        if ($this->userId === null) {
            return null;
        }

        return User::query()->find($this->userId);
    }

    public function render()
    {
        return PlanGridProfiler::measure('ProgramEditor.render', $this->profileContext(), function () {
            if (! in_array($this->activeSection, $this->availableSections, true)) {
                $this->activeSection = $this->availableSections[0] ?? 'main';
                $this->syncSectionFormData();
            }

            return view('livewire.training.view.program-editor');
        });
    }

    public function hydrate(): void
    {
        PlanGridProfiler::mark('ProgramEditor.hydrate', $this->profileContext());
    }

    public function dehydrate(): void
    {
        PlanGridProfiler::mark('ProgramEditor.dehydrate', $this->profileContext());
    }

    protected function profileContext(array $extra = []): array
    {
        return array_merge([
            'component' => static::class,
            'exercise_program_id' => $this->exerciseProgram->id ?? null,
            'plan_id' => $this->planId ?? null,
            'scheduled_training_program_id' => $this->scheduledTrainingProgramId ?? null,
            'user_id' => $this->userId ?? null,
            'weeks' => $this->weeks ?? null,
            'sessions_per_week' => $this->sessionsPerWeek ?? null,
            'active_section' => $this->activeSection ?? null,
            'value_display_mode' => $this->valueDisplayMode ?? null,
            'grid_render_version' => $this->gridRenderVersion ?? null,
            'exercise_count' => isset($this->exerciseProgram) && $this->exerciseProgram->relationLoaded('exercises')
                ? $this->exerciseProgram->exercises->count()
                : null,
        ], $extra);
    }
}
