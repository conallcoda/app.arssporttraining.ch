<?php

namespace App\Livewire\Athlete;

use App\Data\Exercise\DropSet;
use App\Data\Exercise\ExerciseSetting;
use App\Data\Exercise\Settings\AbstractSetting;
use App\Data\Exercise\Settings\RepsSetting;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotSetStatusEnum;
use App\Support\Athlete\ProgramDetailsExerciseViewBuilder;
use App\Support\AthleteDashboardDate;
use App\Support\Training\EffectiveSlotExerciseConfigResolver;
use App\Support\Training\ProgramExerciseOrder;
use App\Support\Training\ScheduledSessionSnapshotBuilder;
use App\Support\Ui\CategoryColorStyle;
use App\Training\AthleteExerciseValueService;
use App\Training\ExerciseGroupLabeler;
use App\Training\TrainingSessionMaterializer;
use App\Training\TrainingSessionProgressService;
use Carbon\CarbonImmutable;
use Coda\FormKit\Field;
use Flux\Flux;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ProgramDetails extends Component
{
    private const SECTION_ORDER = [
        'warm_up',
        'main',
        'warm_down',
    ];

    private const SECTION_LABELS = [
        'warm_up' => 'Warm Up',
        'main' => 'Program',
        'warm_down' => 'Cool Down',
    ];

    private const SETTING_PRIORITY = [
        'reps',
        'weight',
        'distance',
        'duration',
        'pace',
        'watts',
        'heartRate',
        'heartRateZone',
        'tempo',
        'rest',
        'note',
    ];

    public string $date;

    public int $trainingProgramId;

    public ?string $from = null;

    public string $activeSection = 'main';

    public ?int $editingExerciseId = null;

    public string $editingExerciseName = '';

    public string $activeEditSet = '';

    public array $editValues = [];

    public array $editSkippedSets = [];

    public array $pendingSkippedSets = [];

    public bool $previewMode = false;

    public ?int $previewUserId = null;

    public ?int $previewSlotId = null;

    public ?int $initialExerciseId = null;

    public ?int $initialExerciseSort = null;

    protected array $effectiveExerciseConfigs = [];

    public function mount(
        string $date,
        ?TrainingProgram $trainingProgram = null,
        ?string $initialSection = null,
        ?int $initialExerciseId = null,
        ?int $initialExerciseSort = null,
        ?int $previewSlotId = null,
    ): void {
        $this->date = CarbonImmutable::parse($date)->format('Y-m-d');
        $this->from = $this->sanitizeReturnUrl(request()->query('from'));
        $this->previewSlotId = $previewSlotId;

        abort_unless($trainingProgram instanceof TrainingProgram, 404);
        $this->trainingProgramId = $trainingProgram->id;

        $slotExists = TrainingProgramSlot::query()
            ->where('training_program_id', $this->trainingProgramId)
            ->where('user_id', $this->sessionUserId())
            ->when(
                $this->previewSlotId !== null,
                fn ($query) => $query->whereKey($this->previewSlotId),
                fn ($query) => $query->whereDate('datetime', $this->date),
            )
            ->exists();

        abort_unless($slotExists, 404);

        $this->initialExerciseId = $initialExerciseId;
        $this->initialExerciseSort = $initialExerciseSort;

        if ($initialSection !== null && in_array($initialSection, self::SECTION_ORDER, true)) {
            $this->activeSection = $initialSection;
        } else {
            $this->activeSection = $this->resolveInitialSection($trainingProgram);
        }
    }

    #[Computed]
    public function autoExpandSlotExerciseId(): ?int
    {
        if ($this->initialExerciseId === null) {
            return null;
        }

        $snapshot = app(ScheduledSessionSnapshotBuilder::class)->build($this->currentSlot);

        $candidates = collect($snapshot->exercises)
            ->where('type', $this->activeSection)
            ->where('exerciseId', $this->initialExerciseId);

        if ($this->initialExerciseSort !== null) {
            $matched = $candidates->firstWhere('sort', $this->initialExerciseSort);

            if ($matched !== null) {
                return (int) $matched->slotExerciseId;
            }
        }

        $first = $candidates->first();

        return $first === null ? null : (int) $first->slotExerciseId;
    }

    #[Computed]
    public function trainingProgram(): TrainingProgram
    {
        return $this->currentSlot->trainingProgram;
    }

    #[Computed]
    public function currentSlot(): TrainingProgramSlot
    {
        $slot = TrainingProgramSlot::query()
            ->with($this->currentSlotRelations())
            ->where('training_program_id', $this->trainingProgramId)
            ->where('user_id', $this->sessionUserId())
            ->when(
                $this->previewSlotId !== null,
                fn ($query) => $query->whereKey($this->previewSlotId),
                fn ($query) => $query->whereDate('datetime', $this->date),
            )
            ->orderBy('datetime')
            ->orderBy('id')
            ->firstOrFail();

        if ($slot->compiled_at === null) {
            app(TrainingSessionMaterializer::class)->materialize($slot, force: true);

            $slot = $slot->fresh($this->currentSlotRelations());
        } elseif ($this->shouldRefreshAuxiliarySections($slot)) {
            app(TrainingSessionMaterializer::class)->materialize($slot, force: true);

            $slot = $slot->fresh($this->currentSlotRelations());
        }

        return $slot;
    }

    protected function currentSlotRelations(): array
    {
        $relations = [
            'trainingProgram.program.exerciseCategory',
            'trainingProgram.program.exercises',
            'exercises.exercise.category',
            'exercises.exercise.equipment',
            'exercises.exercise.modifiers',
            'exercises.sets.values',
        ];

        if (Schema::hasTable('media')) {
            $relations[] = 'exercises.exercise.media';
        }

        return $relations;
    }

    #[Computed]
    public function programExercises(): array
    {
        $snapshot = app(ScheduledSessionSnapshotBuilder::class)->build($this->currentSlot);

        $sorted = app(ProgramExerciseOrder::class)
            ->sortSlotExercises(
                collect($snapshot->exercises)
                    ->where('type', $this->activeSection)
                    ->values(),
                includeType: false,
            );

        $materializedGroupLabels = ExerciseGroupLabeler::label(
            $sorted,
            fn ($exercise): ?string => $exercise->group,
            fn ($exercise): int => $exercise->slotExerciseId,
        );

        $sourceExercises = app(ProgramExerciseOrder::class)
            ->sortProgramExercises(
                $this->trainingProgram->program->exercises()
                    ->wherePivot('type', $this->activeSection)
                    ->get()
                    ->values(),
                includeType: false,
            );

        $sourceGroupLabelsByIndex = ExerciseGroupLabeler::label(
            $sourceExercises,
            fn ($exercise): ?string => $exercise->pivot->group,
            fn ($exercise): int => $sourceExercises->search($exercise),
        );

        return $sorted
            ->map(fn ($exercise, int $index) => app(ProgramDetailsExerciseViewBuilder::class)->buildFromSnapshot(
                $exercise,
                $index,
                $materializedGroupLabels[$exercise->slotExerciseId] ?? $sourceGroupLabelsByIndex[$index] ?? null,
                $this->pendingSkippedSets[$exercise->slotExerciseId] ?? [],
            ))
            ->values()
            ->all();
    }

    #[Computed]
    public function sectionTabs(): array
    {
        $tabs = collect(self::SECTION_ORDER)
            ->map(function (string $section): ?array {
                $count = $this->currentSlot->exercises
                    ->where('type', $section)
                    ->count();

                if ($count === 0) {
                    return null;
                }

                return [
                    'key' => $section,
                    'label' => self::SECTION_LABELS[$section] ?? Str::headline($section),
                    'count' => $count,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if (! collect($tabs)->pluck('key')->contains($this->activeSection)) {
            $this->activeSection = collect($tabs)->pluck('key')->contains('main')
                ? 'main'
                : (collect($tabs)->first()['key'] ?? 'main');
        }

        return $tabs;
    }

    #[Computed]
    public function showsSectionTabs(): bool
    {
        return collect($this->sectionTabs)
            ->pluck('key')
            ->contains(fn (string $key): bool => $key !== 'main');
    }

    #[Computed]
    public function sectionInstructions(): ?string
    {
        return $this->trainingProgram->program->config->sectionInstructions($this->activeSection);
    }

    private function resolveInitialSection(TrainingProgram $trainingProgram): string
    {
        foreach (self::SECTION_ORDER as $section) {
            if ($trainingProgram->program->exercises()->wherePivot('type', $section)->exists()) {
                return $section;
            }
        }

        return 'main';
    }

    /**
     * @return array<int, array{submitted: bool, color: array{light: string, dark: string}}>
     */
    #[Computed]
    public function progressSegments(): array
    {
        return $this->currentSlot->exercises
            ->pipe(fn ($exercises) => app(ProgramExerciseOrder::class)->sortSlotExercises($exercises))
            ->values()
            ->map(fn (TrainingProgramSlotExercise $exercise) => [
                'submitted' => $exercise->status->isSubmitted(),
                'status' => $exercise->status->value,
                'color' => $exercise->status->barColor(),
            ])
            ->all();
    }

    #[Computed]
    public function completionPercent(): int
    {
        $segments = $this->progressSegments;
        $total = count($segments);

        if ($total === 0) {
            return 0;
        }

        $submitted = array_filter($segments, fn (array $segment): bool => $segment['submitted']);

        return (int) round((count($submitted) / $total) * 100);
    }

    #[Computed]
    public function backUrl(): string
    {
        if ($this->previewMode) {
            return '#';
        }

        return $this->from ?: route('athlete.dashboard.train', ['date' => $this->date]);
    }

    #[Computed]
    public function backLabel(): string
    {
        if ($this->previewMode) {
            return 'Back to Preview';
        }

        return 'Back to Dashboard';
    }

    #[Computed]
    public function athleteEditsEnabled(): bool
    {
        if ($this->previewMode) {
            return false;
        }

        return (bool) config('athlete.allow_athlete_edits', false);
    }

    #[Computed]
    public function canRecordSession(): bool
    {
        if ($this->previewMode) {
            return false;
        }

        return AthleteDashboardDate::canRecordProgramExercisesForDate($this->date);
    }

    #[Computed]
    public function editingExercise(): ?TrainingProgramSlotExercise
    {
        if ($this->editingExerciseId === null) {
            return null;
        }

        $exercise = $this->currentSlot->exercises->firstWhere('id', $this->editingExerciseId);

        return $exercise instanceof TrainingProgramSlotExercise ? $exercise : null;
    }

    #[Computed]
    public function editSetTabs(): array
    {
        return collect($this->editingExercise?->sets ?? [])
            ->sortBy('set_number')
            ->values()
            ->map(fn ($set): array => [
                'name' => 'set-'.$set->id,
                'label' => $this->editSetLabel($set->set_number),
                'isSkipped' => (bool) ($this->editSkippedSets[$set->id] ?? false),
            ])
            ->all();
    }

    #[Computed]
    public function editSetPanels(): array
    {
        $exercise = $this->editingExercise;

        if (! $exercise instanceof TrainingProgramSlotExercise) {
            return [];
        }

        return $exercise->sets
            ->sortBy('set_number')
            ->values()
            ->map(function ($set) use ($exercise): array {
                $fields = collect($set->values)
                    ->sortBy(fn ($value) => $this->settingPriority($value->setting_key))
                    ->map(function ($value) use ($exercise): ?Field {
                        $settingClass = ExerciseSetting::tryFrom($value->setting_key)?->settingClass();

                        if (! is_string($settingClass) || ! is_subclass_of($settingClass, AbstractSetting::class)) {
                            return null;
                        }

                        $field = $settingClass::athleteField(
                            $value->setting_key,
                            $this->resolveSettingConfig($exercise, $value->setting_key)
                        );

                        return $this->applyPlannedValuePlaceholder($field, $settingClass, $value, $exercise);
                    })
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'id' => $set->id,
                    'tab' => 'set-'.$set->id,
                    'label' => $this->editSetLabel($set->set_number),
                    'isSkipped' => (bool) ($this->editSkippedSets[$set->id] ?? false),
                    'fields' => $fields,
                ];
            })
            ->all();
    }

    public function openExerciseEditor(int $slotExerciseId, ?int $setId = null, ?string $fieldKey = null): void
    {
        abort_if(! $this->canRecordSession, 403);

        $exercise = $this->currentSlot->exercises->firstWhere('id', $slotExerciseId);
        abort_unless($exercise instanceof TrainingProgramSlotExercise, 404);

        abort_if(! $this->athleteEditsEnabled, 404);

        $this->prepareExerciseEditor($exercise);

        $setBelongsToExercise = $setId !== null && $exercise->sets->contains('id', $setId);

        if ($setBelongsToExercise) {
            $this->activeEditSet = 'set-'.$setId;
        }

        Flux::modal('athlete-exercise-editor')->show();

        if ($setBelongsToExercise && is_string($fieldKey) && preg_match('/^[A-Za-z0-9_]+$/', $fieldKey)) {
            $this->focusEditField("editValues.{$setId}.{$fieldKey}");
        }
    }

    public function saveExerciseEdits(): void
    {
        abort_if(! $this->athleteEditsEnabled, 404);
        abort_if(! $this->canRecordSession, 403);

        $exercise = $this->editingExercise;
        abort_unless($exercise instanceof TrainingProgramSlotExercise, 404);

        $this->resetValidation();

        $rules = $this->buildEditValidationRules();

        if ($rules !== []) {
            $validator = Validator::make(
                ['editValues' => $this->editValues],
                $rules,
                ['required' => 'This field is required.'],
                $this->buildEditValidationAttributes()
            );

            if ($validator->fails()) {
                $errorKeys = array_keys($validator->errors()->messages());

                $this->setErrorBag($validator->errors());
                $this->activateFirstInvalidEditSet($errorKeys);
                $this->focusFirstInvalidEditField($errorKeys);

                return;
            }
        }

        $this->editValues = $this->castEmptyStringsToNull($this->editValues);

        app(AthleteExerciseValueService::class)->saveExerciseValues($exercise, $this->filterEditableSetValues($this->editValues));
        $this->pendingSkippedSets[$exercise->id] = $this->normalizeSkippedDraft($this->editSkippedSets);

        Flux::modal('athlete-exercise-editor')->close();

        $this->resetEditorState();
        unset($this->programExercises);
    }

    public function cancelExerciseEditor(): void
    {
        $this->resetEditorState();
        Flux::modal('athlete-exercise-editor')->close();
    }

    public function markEditSetSkipped(int $setId): void
    {
        abort_if(! $this->athleteEditsEnabled, 404);
        abort_if(! $this->canRecordSession, 403);

        $this->editSkippedSets[$setId] = true;
        unset($this->editingExercise, $this->editSetTabs, $this->editSetPanels);
    }

    public function markEditSetPending(int $setId): void
    {
        abort_if(! $this->athleteEditsEnabled, 404);
        abort_if(! $this->canRecordSession, 403);

        $this->editSkippedSets[$setId] = false;
        unset($this->editingExercise, $this->editSetTabs, $this->editSetPanels);
    }

    public function markExerciseCompleted(int $slotExerciseId): void
    {
        abort_if($this->previewMode, 403);
        abort_if(! $this->canRecordSession, 403);

        $exercise = $this->currentSlot->exercises->firstWhere('id', $slotExerciseId);
        abort_unless($exercise instanceof TrainingProgramSlotExercise, 404);

        if ($this->pendingSkipDraftSkipsEntireExercise($exercise)) {
            unset($this->pendingSkippedSets[$slotExerciseId]);
            app(TrainingSessionProgressService::class)->markExerciseSkipped($exercise);
            $this->resetEditorState();
            $this->refreshSessionState();
            $this->dispatch('athlete-exercise-action-succeeded', exerciseId: $slotExerciseId);

            return;
        }

        if (! $this->validateExerciseCompletion($exercise)) {
            return;
        }

        $this->persistPendingSkippedSets($exercise);
        app(TrainingSessionProgressService::class)->markExerciseCompleted(
            $exercise,
            completeSkippedSets: $exercise->status === TrainingProgramSlotExerciseStatusEnum::Skipped,
        );
        unset($this->pendingSkippedSets[$slotExerciseId]);

        $this->resetEditorState();
        $this->refreshSessionState();
        $this->dispatch('athlete-exercise-action-succeeded', exerciseId: $slotExerciseId);
    }

    public function markActiveSectionCompleted(): void
    {
        abort_if($this->previewMode, 403);
        abort_if(! $this->canRecordSession, 403);

        $exercises = $this->activeSectionSlotExercises();

        $pendingExercises = $exercises
            ->filter(fn ($exercise): bool => $exercise instanceof TrainingProgramSlotExercise
                && $exercise->status !== TrainingProgramSlotExerciseStatusEnum::Completed)
            ->values();

        $blockedExerciseIds = [];
        $submittedCount = 0;

        foreach ($pendingExercises as $exercise) {
            if (! $exercise instanceof TrainingProgramSlotExercise) {
                continue;
            }

            if ($this->pendingSkipDraftSkipsEntireExercise($exercise)) {
                unset($this->pendingSkippedSets[$exercise->id]);
                app(TrainingSessionProgressService::class)->markExerciseSkipped($exercise);
                $submittedCount++;

                continue;
            }

            if (! $this->validateExerciseCompletion($exercise, showEditor: false)) {
                $blockedExerciseIds[] = $exercise->id;

                continue;
            }

            $this->persistPendingSkippedSets($exercise);
            app(TrainingSessionProgressService::class)->markExerciseCompleted(
                $exercise,
                completeSkippedSets: $exercise->status === TrainingProgramSlotExerciseStatusEnum::Skipped,
            );
            unset($this->pendingSkippedSets[$exercise->id]);
            $submittedCount++;
        }

        if ($submittedCount > 0) {
            $this->resetEditorState();
            $this->refreshSessionState();
            $this->dispatch('athlete-section-action-succeeded', section: $this->activeSection);
        }

        if ($blockedExerciseIds === []) {
            return;
        }

        $blockedExercise = $this->currentSlot->exercises->firstWhere('id', $blockedExerciseIds[0]);

        if ($blockedExercise instanceof TrainingProgramSlotExercise) {
            $this->validateExerciseCompletion($blockedExercise);
        }

        $blockedCount = count($blockedExerciseIds);
        $message = $submittedCount > 0
            ? trans_choice(
                ':submitted exercise marked done. :blocked exercise needs values before it can be completed.|:submitted exercises marked done. :blocked exercises need values before they can be completed.',
                $blockedCount,
                ['submitted' => $submittedCount, 'blocked' => $blockedCount],
            )
            : trans_choice(
                ':blocked exercise needs values before it can be completed.|:blocked exercises need values before they can be completed.',
                $blockedCount,
                ['blocked' => $blockedCount],
            );

        Flux::toast(text: $message, variant: 'warning');
    }

    public function markActiveSectionSkipped(): void
    {
        abort_if($this->previewMode, 403);
        abort_if(! $this->canRecordSession, 403);

        $exercises = $this->activeSectionSlotExercises();

        $pendingExercises = $exercises
            ->filter(fn ($exercise): bool => $exercise instanceof TrainingProgramSlotExercise
                && $exercise->status !== TrainingProgramSlotExerciseStatusEnum::Skipped)
            ->values();

        $this->resetEditorState();

        foreach ($pendingExercises as $exercise) {
            if (! $exercise instanceof TrainingProgramSlotExercise) {
                continue;
            }

            unset($this->pendingSkippedSets[$exercise->id]);
            app(TrainingSessionProgressService::class)->markExerciseSkipped($exercise);
        }

        $this->refreshSessionState();
        $this->dispatch('athlete-section-action-succeeded', section: $this->activeSection);
    }

    public function markActiveSectionPending(): void
    {
        abort_if($this->previewMode, 403);
        abort_if(! $this->canRecordSession, 403);

        $exercises = $this->activeSectionSlotExercises()
            ->filter(fn ($exercise): bool => $exercise instanceof TrainingProgramSlotExercise
                && $exercise->status !== TrainingProgramSlotExerciseStatusEnum::Pending)
            ->values();

        $this->resetEditorState();

        foreach ($exercises as $exercise) {
            if (! $exercise instanceof TrainingProgramSlotExercise) {
                continue;
            }

            unset($this->pendingSkippedSets[$exercise->id]);
            app(TrainingSessionProgressService::class)->markExercisePending($exercise);
        }

        $this->refreshSessionState();
        $this->dispatch('athlete-section-action-succeeded', section: $this->activeSection);
    }

    public function markExerciseSkipped(int $slotExerciseId): void
    {
        abort_if($this->previewMode, 403);
        abort_if(! $this->canRecordSession, 403);

        $exercise = $this->currentSlot->exercises->firstWhere('id', $slotExerciseId);
        abort_unless($exercise instanceof TrainingProgramSlotExercise, 404);

        unset($this->pendingSkippedSets[$slotExerciseId]);
        app(TrainingSessionProgressService::class)->markExerciseSkipped($exercise);

        $this->refreshSessionState();
        $this->dispatch('athlete-exercise-action-succeeded', exerciseId: $slotExerciseId);
    }

    protected function activeSectionSlotExercises()
    {
        return app(ProgramExerciseOrder::class)
            ->sortSlotExercises(
                $this->currentSlot->exercises
                    ->where('type', $this->activeSection)
                    ->values(),
                includeType: false,
            );
    }

    protected function shouldRefreshAuxiliarySections(TrainingProgramSlot $slot): bool
    {
        $sourceHasAuxiliarySections = $slot->trainingProgram->program->exercises
            ->contains(fn ($exercise): bool => ($exercise->pivot->type ?? 'main') !== 'main');

        if (! $sourceHasAuxiliarySections) {
            return false;
        }

        $slotHasAuxiliarySections = $slot->exercises
            ->contains(fn (TrainingProgramSlotExercise $exercise): bool => in_array($exercise->type ?? 'main', ['warm_up', 'warm_down'], true));

        if ($slotHasAuxiliarySections) {
            return false;
        }

        return (int) $slot->completed_exercise_count === 0
            && (int) $slot->partial_exercise_count === 0
            && (int) $slot->skipped_exercise_count === 0
            && ! $slot->has_any_modification;
    }

    #[Computed]
    public function isFutureSession(): bool
    {
        return AthleteDashboardDate::isFutureDate($this->date);
    }

    public function exitPreviewDetails(): void
    {
        if (! $this->previewMode) {
            return;
        }

        $this->dispatch('athlete-preview-back');
    }

    protected function sanitizeReturnUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (Str::startsWith($url, '/')) {
            return $url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $appUrl = parse_url(url('/'));
        $returnUrl = parse_url($url);

        if (($appUrl['host'] ?? null) !== ($returnUrl['host'] ?? null)) {
            return null;
        }

        $path = $returnUrl['path'] ?? '/';
        $query = isset($returnUrl['query']) ? '?'.$returnUrl['query'] : '';
        $fragment = isset($returnUrl['fragment']) ? '#'.$returnUrl['fragment'] : '';

        return $path.$query.$fragment;
    }

    public function categoryBadgeStyle(?string $color): ?string
    {
        return CategoryColorStyle::resolve($color);
    }

    protected function buildEditValues(TrainingProgramSlotExercise $exercise): array
    {
        return $exercise->sets
            ->sortBy('set_number')
            ->mapWithKeys(function ($set) use ($exercise): array {
                $values = $set->values
                    ->mapWithKeys(function ($value) use ($exercise): array {
                        $settingClass = ExerciseSetting::tryFrom($value->setting_key)?->settingClass();
                        $config = $this->resolveSettingConfig($exercise, $value->setting_key);
                        $resolvedValue = $this->extractResolvedValue($value);

                        return [$value->setting_key => $this->resolveEditInputValue(
                            $settingClass,
                            $resolvedValue,
                            $value->unit,
                            $config
                        )];
                    })
                    ->all();

                return [$set->id => $values];
            })
            ->all();
    }

    protected function buildEditSkippedSets(TrainingProgramSlotExercise $exercise): array
    {
        return $exercise->sets
            ->mapWithKeys(fn ($set): array => [
                $set->id => (string) ($set->status->value ?? $set->status) === TrainingProgramSlotSetStatusEnum::Skipped->value,
            ])
            ->all();
    }

    /**
     * @return array<string, string|array<int, string>>
     */
    protected function buildEditValidationRules(): array
    {
        $rules = [];

        foreach ($this->editSetPanels as $panel) {
            if ($panel['isSkipped']) {
                continue;
            }

            $rules = array_merge(
                $rules,
                Field::buildValidationRules(
                    $panel['fields'],
                    'editValues.'.$panel['id'].'.',
                    $this->editValues[$panel['id']] ?? []
                )
            );
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function buildEditValidationAttributes(): array
    {
        $attributes = [];

        foreach ($this->editSetPanels as $panel) {
            if ($panel['isSkipped']) {
                continue;
            }

            $attributes = array_merge(
                $attributes,
                Field::buildValidationAttributes($panel['fields'], 'editValues.'.$panel['id'].'.')
            );
        }

        return $attributes;
    }

    protected function editSetLabel(int $setNumber): string
    {
        $baseLabel = (string) ($this->editingExercise instanceof TrainingProgramSlotExercise
            ? ($this->resolveExerciseConfig($this->editingExercise)['sets']['label'] ?? 'Set')
            : 'Set');

        return trim($baseLabel).' '.$setNumber;
    }

    protected function resolveSettingConfig(TrainingProgramSlotExercise $exercise, string $settingKey): array
    {
        $exerciseConfig = $this->resolveExerciseConfig($exercise);
        $config = $exerciseConfig[$settingKey] ?? null;
        $sets = $exerciseConfig['sets'] ?? [];
        $expectedPartCount = DropSet::expectedPartCount($exerciseConfig);

        if (is_array($config)) {
            $config['_sets'] = is_array($sets) ? $sets : [];
            $config['_drop_set_part_count'] = $expectedPartCount;

            return $config;
        }

        $settingConfig = is_object($config) && method_exists($config, 'toArray') ? $config->toArray() : [];
        $settingConfig['_sets'] = is_array($sets) ? $sets : [];
        $settingConfig['_drop_set_part_count'] = $expectedPartCount;

        return $settingConfig;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveExerciseConfig(TrainingProgramSlotExercise $exercise): array
    {
        return $this->effectiveExerciseConfigs[$exercise->id]
            ??= app(EffectiveSlotExerciseConfigResolver::class)->resolve($exercise);
    }

    protected function extractResolvedValue($value): mixed
    {
        if ($value->actual_value_type !== null) {
            return match ($value->actual_value_type) {
                'int' => $value->actual_int_value,
                'decimal' => $value->actual_decimal_value !== null ? (float) $value->actual_decimal_value : null,
                'json' => $value->actual_json_value,
                default => $value->actual_string_value,
            };
        }

        return match ($value->planned_value_type) {
            'int' => $value->planned_int_value,
            'decimal' => $value->planned_decimal_value !== null ? (float) $value->planned_decimal_value : null,
            'json' => $value->planned_json_value,
            default => $value->planned_string_value,
        };
    }

    protected function settingPriority(string $settingKey): int
    {
        $index = array_search($settingKey, self::SETTING_PRIORITY, true);

        return $index === false ? PHP_INT_MAX : $index;
    }

    protected function resolveEditInputValue(?string $settingClass, mixed $value, ?string $unit, array $config): mixed
    {
        if (! is_string($settingClass) || ! is_subclass_of($settingClass, AbstractSetting::class)) {
            return $value;
        }

        if ($settingClass === RepsSetting::class && RepsSetting::requiresAthleteSpecificValue($value)) {
            return '';
        }

        if (($config['unit'] ?? null) === 'mm:ss') {
            return $settingClass::formatAthleteValue($value, $unit, $config);
        }

        return $value;
    }

    protected function applyPlannedValuePlaceholder(Field $field, string $settingClass, mixed $value, TrainingProgramSlotExercise $exercise): Field
    {
        if (! method_exists($field, 'placeholder') || $value->planned_value_type === null) {
            return $field;
        }

        $plannedValue = match ($value->planned_value_type) {
            'int' => $value->planned_int_value,
            'decimal' => $value->planned_decimal_value !== null ? (float) $value->planned_decimal_value : null,
            'json' => $value->planned_json_value,
            default => $value->planned_string_value,
        };

        if ($plannedValue === null || $plannedValue === '') {
            return $field;
        }

        $config = $this->resolveSettingConfig($exercise, $value->setting_key);
        $placeholder = is_string($settingClass) && is_subclass_of($settingClass, AbstractSetting::class)
            ? ($settingClass::formatAthleteValue($plannedValue, $value->unit, $config) ?? $plannedValue)
            : $plannedValue;

        if ($placeholder === null || $placeholder === '') {
            return $field;
        }

        $field->placeholder((string) $placeholder);

        return $field;
    }

    protected function resetEditorState(): void
    {
        $this->editingExerciseId = null;
        $this->editingExerciseName = '';
        $this->activeEditSet = '';
        $this->editValues = [];
        $this->editSkippedSets = [];
        $this->effectiveExerciseConfigs = [];

        unset($this->editingExercise, $this->editSetTabs, $this->editSetPanels);
    }

    protected function refreshSessionState(): void
    {
        $this->effectiveExerciseConfigs = [];

        unset($this->currentSlot, $this->programExercises, $this->progressSegments, $this->sectionTabs, $this->showsSectionTabs, $this->editingExercise, $this->editSetTabs, $this->editSetPanels);
    }

    protected function prepareExerciseEditor(TrainingProgramSlotExercise $exercise): void
    {
        $this->editingExerciseId = $exercise->id;
        $this->editingExerciseName = (string) ($exercise->exercise?->name ?? __('Exercise'));
        $this->editValues = $this->buildEditValues($exercise);
        $this->editSkippedSets = $this->pendingSkippedSets[$exercise->id] ?? $this->buildEditSkippedSets($exercise);
        $this->activeEditSet = 'set-'.($exercise->sets->sortBy('set_number')->first()?->id ?? '');

        unset($this->editingExercise, $this->editSetTabs, $this->editSetPanels);
    }

    protected function validateExerciseCompletion(TrainingProgramSlotExercise $exercise, bool $showEditor = true): bool
    {
        $this->prepareExerciseEditor($exercise);
        $this->resetValidation();

        $rules = $this->buildEditValidationRules();

        if ($rules === []) {
            return true;
        }

        $validator = Validator::make(
            ['editValues' => $this->editValues],
            $rules,
            ['required' => 'This field is required.'],
            $this->buildEditValidationAttributes()
        );

        if ($validator->fails()) {
            if ($showEditor) {
                $this->setErrorBag($validator->errors());
                $this->activateFirstInvalidEditSet(array_keys($validator->errors()->messages()));
                Flux::modal('athlete-exercise-editor')->show();
            }

            return false;
        }

        return true;
    }

    /**
     * @param  array<int, string>  $errorKeys
     */
    protected function activateFirstInvalidEditSet(array $errorKeys): void
    {
        foreach ($errorKeys as $key) {
            if (! preg_match('/^editValues\.(\d+)\./', $key, $matches)) {
                continue;
            }

            $this->activeEditSet = 'set-'.$matches[1];

            return;
        }
    }

    /**
     * @param  array<int, string>  $errorKeys
     */
    protected function focusFirstInvalidEditField(array $errorKeys): void
    {
        foreach ($errorKeys as $key) {
            if (! preg_match('/^editValues\.\d+\.[A-Za-z0-9_]+$/', $key)) {
                continue;
            }

            $this->focusEditField($key);

            return;
        }
    }

    protected function focusEditField(string $model): void
    {
        $this->dispatch('athlete-exercise-editor-focus', model: $model);
    }

    protected function filterEditableSetValues(array $data): array
    {
        return collect($data)
            ->reject(fn ($_values, $setId): bool => (bool) ($this->editSkippedSets[(int) $setId] ?? false))
            ->all();
    }

    protected function normalizeSkippedDraft(array $draft): array
    {
        return collect($draft)
            ->map(fn (mixed $value): bool => (bool) $value)
            ->all();
    }

    protected function persistPendingSkippedSets(TrainingProgramSlotExercise $exercise): void
    {
        $draft = $this->pendingSkippedSets[$exercise->id] ?? [];

        if ($draft === []) {
            return;
        }

        foreach ($exercise->sets as $set) {
            $shouldBeSkipped = (bool) ($draft[$set->id] ?? false);
            $isSkipped = (string) ($set->status->value ?? $set->status) === TrainingProgramSlotSetStatusEnum::Skipped->value;

            if ($shouldBeSkipped && ! $isSkipped) {
                app(TrainingSessionProgressService::class)->markSetSkipped($set);
            }

            if (! $shouldBeSkipped && $isSkipped) {
                app(TrainingSessionProgressService::class)->markSetPending($set);
            }
        }
    }

    protected function pendingSkipDraftSkipsEntireExercise(TrainingProgramSlotExercise $exercise): bool
    {
        $draft = $this->pendingSkippedSets[$exercise->id] ?? [];

        if ($draft === []) {
            return false;
        }

        foreach ($exercise->sets as $set) {
            if (! ($draft[$set->id] ?? false)) {
                return false;
            }
        }

        return true;
    }

    protected function castEmptyStringsToNull(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->castEmptyStringsToNull($value);

                continue;
            }

            if ($value === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }

    protected function sessionUserId(): int
    {
        return $this->previewMode
            ? (int) ($this->previewUserId ?? 0)
            : (int) auth()->id();
    }

    public function render(): View
    {
        // Resolve the available materialized sections before filtering exercises.
        // The source program can contain a section whose exercises are disabled
        // for this athlete, so the slot remains the source of truth here.
        $sectionTabs = $this->sectionTabs;

        return view('livewire.athlete.program-details', [
            'trainingProgram' => $this->trainingProgram,
            'currentSlot' => $this->currentSlot,
            'programExercises' => $this->programExercises,
            'sectionTabs' => $sectionTabs,
            'showsSectionTabs' => $this->showsSectionTabs,
            'isFutureSession' => $this->isFutureSession,
            'canRecordSession' => $this->canRecordSession,
            'athleteEditsEnabled' => $this->athleteEditsEnabled,
            'previewMode' => $this->previewMode,
            'autoExpandExerciseId' => $this->autoExpandSlotExerciseId,
        ])->layout('components.layouts.athlete', ['title' => $this->trainingProgram->program->name]);
    }
}
