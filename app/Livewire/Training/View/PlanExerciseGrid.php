<?php

namespace App\Livewire\Training\View;

use App\Data\Coach\Settings\SessionGroupingSetting;
use App\Data\Exercise\DropSet;
use App\Data\Exercise\ExerciseConfig;
use App\Data\Exercise\ExerciseSetting;
use App\Data\Exercise\Preview\ExercisePreviewBuilder;
use App\Data\Exercise\Preview\GridOverrides;
use App\Data\Exercise\Preview\OverrideManager;
use App\Data\Exercise\Preview\PreviewGrid;
use App\Data\Exercise\Preview\SessionGroupBuilder;
use App\Data\Exercise\Preview\SessionGroupingConfig;
use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Data\Exercise\Settings\AbstractSetting;
use App\Data\Exercise\Settings\RepsSetting;
use App\Data\Exercise\Settings\SetsSetting;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Training\Config\EffectiveExerciseConfig;
use App\Data\Training\Config\ExerciseOverrides;
use App\Data\Training\Config\ExercisePlanConfig;
use App\Data\Training\Config\ResolvedExerciseOverrides;
use App\Data\Training\Snapshot\ScheduledExerciseSnapshotData;
use App\Data\Training\Snapshot\ScheduledSessionSnapshotData;
use App\Data\Training\Snapshot\ScheduledSetSnapshotData;
use App\Data\Training\Snapshot\ScheduledValueSnapshotData;
use App\Livewire\Concerns\InteractsWithDisplayGridCopying;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSet;
use App\Models\Training\TrainingProgramSlotSetStatusEnum;
use App\Models\Training\TrainingProgramSlotSetValue;
use App\Models\Users\UserTypeEnum;
use App\Support\AthleteDashboardDate;
use App\Support\Profiling\PlanGridProfiler;
use App\Support\Training\ApplyPerScope;
use App\Support\Training\ExerciseMetricAvailability;
use App\Support\Training\GridOverrideNormalizer;
use App\Support\Training\ScheduledSessionSnapshotBuilder;
use App\Support\Training\SlotStatusPresenter;
use App\Support\Training\WeekSessionCountResolver;
use App\Training\AthleteExerciseValueService;
use App\Training\TrainingPlanRevisionService;
use App\Training\TrainingSessionEditGuard;
use App\Training\TrainingSessionPlannedValueService;
use App\Training\TrainingSessionProgressService;
use App\Training\TrainingSessionRebuildDispatcher;
use ArrayObject;
use Carbon\Carbon;
use Coda\Cms\Livewire\Concerns\InteractsWithParentView;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class PlanExerciseGrid extends Component
{
    use InteractsWithDisplayGridCopying;
    use InteractsWithParentView;

    private const SCHEDULED_DATA_CACHE_KEY = 'plan-exercise-grid-scheduled-data-cache';

    public int $planId;

    public array $planConfigArray = [];

    public int $programExerciseId;

    public int $exerciseId;

    #[Reactive]
    public ?int $userId = null;

    public int $weeks;

    public int $sessionsPerWeek;

    public array $weekLabels = [];

    public array $weekSessions = [];

    public array $weekSessionDates = [];

    public array $weekSessionDateRanges = [];

    public array $expandedWeeks = [];

    public array $lockedSessionsByWeek = [];

    public array $sessionStatusesByWeek = [];

    public bool $sessionLabels = false;

    public bool $showActualValueTabs = false;

    public bool $showPlannedActualInline = false;

    #[Reactive]
    public string $valueDisplayMode = 'planned';

    public string $exerciseName = '';

    public int $programExerciseSort = 0;

    public string $programExerciseType = 'main';

    public int|string|null $programExerciseGroup = null;

    public ?int $scheduledTrainingProgramId = null;

    #[Reactive]
    public ?string $groupLabel = null;

    public array $exerciseConfigArray = [];

    /** @var array<int, array{label: string, color: string}> */
    public array $exerciseBadges = [];

    public int $gridRenderVersion = 0;

    public ?string $pendingRecordAction = null;

    public ?int $pendingRecordWeek = null;

    public ?int $pendingRecordSession = null;

    protected ?array $loadedScheduledSlotsByDate = null;

    protected ?bool $canManageSessionRecordsCache = null;

    protected ?array $loadedScheduledSnapshotsByDate = null;

    public static function flushScheduledDataCaches(): void
    {
        if (app()->bound(self::SCHEDULED_DATA_CACHE_KEY)) {
            app()->forgetInstance(self::SCHEDULED_DATA_CACHE_KEY);
        }
    }

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

    public function mount(
        int $planId,
        int $programExerciseId,
        int $exerciseId,
        ?int $userId,
        int $weeks,
        int $sessionsPerWeek,
        ?int $scheduledTrainingProgramId = null,
        ?int $planMeasuredReps = null,
        ?float $planMeasuredWeight = null,
        int|float|null $planTargetGoal = 10,
        ?int $planMaxHR = null,
        ?int $planIatPercent = null,
        array $weekLabels = [],
        array $weekSessions = [],
        array $weekSessionDates = [],
        array $weekSessionDateRanges = [],
        array $expandedWeeks = [],
        array $lockedSessionsByWeek = [],
        array $sessionStatusesByWeek = [],
        array $calendarWeekSchedule = [],
        bool $sessionLabels = false,
        bool $showActualValueTabs = false,
        string $valueDisplayMode = 'planned',
        ?string $groupLabel = null,
        array $planConfigArray = [],
        ?string $exerciseName = null,
        array $exerciseConfigArray = [],
        array $exerciseBadges = [],
        int $programExerciseSort = 0,
        string $programExerciseType = 'main',
        int|string|null $programExerciseGroup = null,
    ): void {
        $span = PlanGridProfiler::start('PlanExerciseGrid.mount', [
            'plan_id' => $planId,
            'program_exercise_id' => $programExerciseId,
            'exercise_id' => $exerciseId,
            'user_id' => $userId,
            'scheduled_training_program_id' => $scheduledTrainingProgramId,
            'weeks' => $weeks,
            'sessions_per_week' => $sessionsPerWeek,
            'week_session_dates_count' => collect($weekSessionDates)->flatten()->count(),
            'has_prefetched_exercise' => $exerciseName !== null && $exerciseConfigArray !== [],
        ]);

        try {
            $this->planId = $planId;
            $this->programExerciseId = $programExerciseId;
            $this->exerciseId = $exerciseId;
            $this->userId = $userId;
            $this->planConfigArray = $this->slicePlanConfigArray($planConfigArray);
            $this->scheduledTrainingProgramId = $scheduledTrainingProgramId;
            $this->weeks = $weeks;
            $this->sessionsPerWeek = $sessionsPerWeek;
            $this->planMeasuredReps = $planMeasuredReps;
            $this->planMeasuredWeight = $planMeasuredWeight;
            $this->planTargetGoal = $planTargetGoal;
            $this->planMaxHR = $planMaxHR;
            $this->planIatPercent = $planIatPercent;
            $this->weekLabels = $weekLabels;
            $this->weekSessions = $weekSessions;
            $this->weekSessionDates = $weekSessionDates;
            $this->weekSessionDateRanges = $weekSessionDateRanges;
            $this->expandedWeeks = $expandedWeeks;
            $this->lockedSessionsByWeek = $lockedSessionsByWeek;
            $this->sessionStatusesByWeek = $sessionStatusesByWeek;
            $this->sessionLabels = $sessionLabels;
            $this->showActualValueTabs = $showActualValueTabs;
            $this->valueDisplayMode = $valueDisplayMode;
            $this->groupLabel = $groupLabel;

            if ($exerciseName !== null && $exerciseConfigArray !== []) {
                $this->exerciseName = $exerciseName;
                $this->exerciseConfigArray = $exerciseConfigArray;
                $this->exerciseBadges = $exerciseBadges;
                $this->programExerciseSort = $programExerciseSort;
                $this->programExerciseType = $programExerciseType;
                $this->programExerciseGroup = $programExerciseGroup;
            } else {
                $exercise = Exercise::with(['equipment', 'modifiers'])->findOrFail($exerciseId);
                $this->exerciseName = $exercise->name;
                $this->exerciseConfigArray = $exercise->config->toArray();
                $this->exerciseBadges = $this->buildExerciseBadges($exercise);

                $programExercise = ExerciseProgramExercise::findOrFail($programExerciseId);
                $this->programExerciseSort = (int) ($programExercise->sort ?? 0);
                $this->programExerciseType = (string) ($programExercise->type ?? 'main');
                $this->programExerciseGroup = $programExercise->group;
            }

            if ($this->scheduledTrainingProgramId === null) {
                $this->scheduledTrainingProgramId = $this->resolveScheduledTrainingProgramId($planId);
            }

            $this->applyCalendarWeekScheduleIfNeeded($calendarWeekSchedule);
            $this->syncExerciseSessionLocks();
        } finally {
            PlanGridProfiler::end($span);
        }
    }

    protected function applyCalendarWeekScheduleIfNeeded(array $calendarWeekSchedule): void
    {
        $groupingMode = SessionGroupingMode::normalizeMode(
            (string) data_get($this->getEffectiveConfig(), 'preview.groupingMode'),
        );

        if ($groupingMode !== SessionGroupingMode::Week->value || $calendarWeekSchedule === []) {
            return;
        }

        $this->weeks = max(1, (int) ($calendarWeekSchedule['weeks'] ?? 1));
        $this->sessionsPerWeek = max(1, (int) ($calendarWeekSchedule['sessionsPerWeek'] ?? 1));
        $this->weekLabels = $calendarWeekSchedule['weekLabels'] ?? [];
        $this->weekSessions = $calendarWeekSchedule['weekSessions'] ?? [];
        $this->weekSessionDates = $calendarWeekSchedule['weekSessionDates'] ?? [];
        $this->weekSessionDateRanges = $calendarWeekSchedule['weekSessionDateRanges'] ?? [];
        $this->expandedWeeks = $calendarWeekSchedule['expandedWeeks'] ?? [];
        $this->lockedSessionsByWeek = $calendarWeekSchedule['lockedSessionsByWeek'] ?? [];
        $this->sessionStatusesByWeek = $calendarWeekSchedule['exerciseSessionStatusesByWeek']['program-exercise-'.$this->programExerciseId]
            ?? $calendarWeekSchedule['sessionStatusesByWeek']
            ?? [];
    }

    public function previewSession(int $week, int $session): void
    {
        if ($this->userId === null || $this->scheduledTrainingProgramId === null) {
            return;
        }

        $sessionKey = $this->previewSlotIdForWeekSession($week, $session);

        if ($sessionKey === null) {
            return;
        }

        $this->dispatch(
            'open-program-preview-at-session',
            sessionKey: (string) $sessionKey,
            section: $this->programExerciseType,
            exerciseId: $this->exerciseId,
            exerciseSort: $this->programExerciseSort,
        );
    }

    #[Computed]
    public function recordMenuOptions(): array
    {
        if (! $this->showsRecordEditingMode()
            || $this->userId === null
            || $this->scheduledTrainingProgramId === null
            || ! $this->canManageSessionRecords()) {
            return [];
        }

        return collect($this->previewMenuOptions)
            ->map(fn (array $sessions): array => collect($sessions)
                ->filter(fn (array $session): bool => $this->canRecordWeekSession(
                    (int) $session['week'],
                    (int) $session['session'],
                ))
                ->map(function (array $session): array {
                    $exercise = $this->slotExerciseForWeekSession(
                        (int) $session['week'],
                        (int) $session['session'],
                    );

                    return $session + [
                        'status' => $exercise?->status?->value ?? 'pending',
                    ];
                })
                ->values()
                ->all())
            ->filter()
            ->all();
    }

    public function requestRecordAction(int $week, int $session, string $action): void
    {
        abort_unless(in_array($action, ['edit', 'skipped', 'completed', 'pending'], true), 422);
        abort_unless($this->showsRecordEditingMode(), 403);
        abort_unless($this->canManageSessionRecords(), 403);
        abort_unless($this->canRecordWeekSession($week, $session), 403);

        $exercise = $this->slotExerciseForWeekSession($week, $session);
        abort_unless($exercise instanceof TrainingProgramSlotExercise, 404);
        abort_unless($this->recordActionAllowedForExercise($action, $exercise), 422);

        if (in_array($action, ['edit', 'pending'], true)) {
            $this->performRecordAction($week, $session, $action);

            return;
        }

        if ($this->exerciseHasStoredActualValues($exercise)) {
            $this->pendingRecordAction = $action;
            $this->pendingRecordWeek = $week;
            $this->pendingRecordSession = $session;
            Flux::modal($this->recordConfirmationModalName())->show();

            return;
        }

        $this->performRecordAction($week, $session, $action);
    }

    public function confirmRecordAction(): void
    {
        abort_unless($this->showsRecordEditingMode(), 403);
        abort_unless($this->canManageSessionRecords(), 403);
        abort_unless($this->pendingRecordAction !== null, 422);
        abort_unless($this->pendingRecordWeek !== null && $this->pendingRecordSession !== null, 422);

        $action = $this->pendingRecordAction;
        $week = $this->pendingRecordWeek;
        $session = $this->pendingRecordSession;

        $this->clearPendingRecordAction();
        Flux::modal($this->recordConfirmationModalName())->close();

        $this->performRecordAction($week, $session, $action);
    }

    public function cancelRecordAction(): void
    {
        $this->clearPendingRecordAction();
        Flux::modal($this->recordConfirmationModalName())->close();
    }

    public function recordConfirmationModalName(): string
    {
        return 'confirm-record-action-'.$this->programExerciseId;
    }

    public function pendingRecordActionLabel(): string
    {
        return match ($this->pendingRecordAction) {
            'skipped' => __('Mark as Skipped'),
            'completed' => __('Mark as Completed'),
            'pending' => __('Mark as Pending'),
            default => __('Continue'),
        };
    }

    protected function performRecordAction(int $week, int $session, string $action): void
    {
        abort_unless(in_array($action, ['edit', 'skipped', 'completed', 'pending'], true), 422);
        abort_unless($this->showsRecordEditingMode(), 403);
        abort_unless($this->canManageSessionRecords(), 403);
        abort_unless($this->canRecordWeekSession($week, $session), 403);

        $exercise = $this->slotExerciseForWeekSession($week, $session);
        abort_unless($exercise instanceof TrainingProgramSlotExercise, 404);
        abort_unless($this->recordActionAllowedForExercise($action, $exercise), 422);

        if ($action === 'edit') {
            $this->dispatch(
                'open-program-record-at-session',
                sessionKey: (string) $exercise->training_program_slot_id,
                section: $this->programExerciseType,
                exerciseId: $this->exerciseId,
                exerciseSort: $this->programExerciseSort,
            );

            return;
        }

        $progress = app(TrainingSessionProgressService::class);

        match ($action) {
            'skipped' => $progress->markExerciseSkipped($exercise, clearActualValues: true),
            'completed' => $progress->markExerciseCompleted($exercise, clearActualValues: true),
            'pending' => $progress->markExercisePending($exercise, clearActualValues: true),
        };

        $this->syncDisplayedSessionStatus($week, $session, $exercise->fresh());

        if ($action === 'pending') {
            $this->lockedSessionsByWeek[$week][$session] = false;
            $this->restoreHistoricalPlanForPendingSession($week, $session);
        }

        $this->refreshRecordedSessionData();
        Flux::toast(text: __('Session record updated.'), variant: 'success');
    }

    protected function syncDisplayedSessionStatus(int $week, int $session, TrainingProgramSlotExercise $exercise): void
    {
        $status = $exercise->status?->value ?? 'pending';
        $presenter = app(SlotStatusPresenter::class);

        $this->sessionStatusesByWeek[$week][$session] = [
            'value' => $status,
            'label' => $presenter->label($status),
            'color' => $presenter->color($status),
        ];

        unset($this->displayGrid, $this->recordMenuOptions);
    }

    protected function restoreHistoricalPlanForPendingSession(int $week, int $session): void
    {
        $overrides = $this->getCurrentOverrides();
        $before = $overrides->historicalGridOverrides;
        $current = $overrides->gridOverrides;

        foreach ($before['cells'] ?? [] as $cellOverride) {
            if ((int) ($cellOverride['week'] ?? -1) !== $week || (int) ($cellOverride['session'] ?? -1) !== $session) {
                continue;
            }

            $set = (int) ($cellOverride['set'] ?? 0);

            foreach ((array) ($cellOverride['data'] ?? []) as $field => $value) {
                if ($this->planningValuesEquivalent($value, $this->getEffectiveCellDefault((string) $field, $week, $set, $session))) {
                    continue;
                }

                $current = $this->putCellOverride($current, $week, $session, $set, (string) $field, $value);
            }
        }

        foreach ($before['sessions'] ?? [] as $sessionOverride) {
            if ((int) ($sessionOverride['week'] ?? -1) !== $week || (int) ($sessionOverride['session'] ?? -1) !== $session) {
                continue;
            }

            foreach ((array) ($sessionOverride['data'] ?? []) as $field => $value) {
                if ($this->planningValuesEquivalent($value, $this->getEffectiveSessionDefault((string) $field, $week, $session))) {
                    continue;
                }

                $current = $this->putSessionOverride($current, $week, $session, (string) $field, $value);
            }
        }

        $after = [
            'sessions' => collect($before['sessions'] ?? [])
                ->reject(fn (array $override): bool => (int) ($override['week'] ?? -1) === $week
                    && (int) ($override['session'] ?? -1) === $session)
                ->values()
                ->all(),
            'cells' => collect($before['cells'] ?? [])
                ->reject(fn (array $override): bool => (int) ($override['week'] ?? -1) === $week
                    && (int) ($override['session'] ?? -1) === $session)
                ->values()
                ->all(),
        ];

        if ($before === $after && $current === $overrides->gridOverrides) {
            return;
        }

        $overrides->gridOverrides = $current;
        $overrides->historicalGridOverrides = $after;
        $this->saveOverrides($overrides, notifyParent: false, snapshotLockedWeeks: false);

        $exerciseProgram = ExerciseProgram::query()->findOrFail($this->planId);

        app(TrainingPlanRevisionService::class)->recordGridOverrideChanges(
            owner: $exerciseProgram,
            programExerciseId: $this->programExerciseId,
            userId: $this->userId,
            before: $before,
            after: $after,
            fieldConfigMap: $this->planRevisionFieldConfigMap(),
            action: 'mark_exercise_pending_archive_historical',
        );
    }

    protected function planningValuesEquivalent(mixed $left, mixed $right): bool
    {
        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left === (float) $right;
        }

        return $left === $right || (string) $left === (string) $right;
    }

    protected function exerciseHasStoredActualValues(TrainingProgramSlotExercise $exercise): bool
    {
        return TrainingProgramSlotSetValue::query()
            ->whereHas('slotSet', fn ($query) => $query->where('training_program_slot_exercise_id', $exercise->id))
            ->where(function ($query): void {
                $query->whereNotNull('actual_value_type')
                    ->orWhereNotNull('actual_int_value')
                    ->orWhereNotNull('actual_decimal_value')
                    ->orWhereNotNull('actual_string_value')
                    ->orWhereNotNull('actual_json_value')
                    ->orWhereNotNull('actual_recorded_at');
            })
            ->exists();
    }

    protected function recordActionAllowedForExercise(string $action, TrainingProgramSlotExercise $exercise): bool
    {
        $status = $exercise->status?->value ?? 'pending';

        return match ($action) {
            'edit' => true,
            'skipped' => $status !== 'skipped',
            'completed' => in_array($status, ['pending', 'skipped'], true),
            'pending' => $status !== 'pending',
            default => false,
        };
    }

    protected function canManageSessionRecords(): bool
    {
        if ($this->canManageSessionRecordsCache !== null) {
            return $this->canManageSessionRecordsCache;
        }

        $user = Auth::user();

        return $this->canManageSessionRecordsCache = in_array(
            $user?->type,
            [UserTypeEnum::Coach, UserTypeEnum::Admin],
            true,
        );
    }

    protected function canRecordWeekSession(int $week, int $session): bool
    {
        $date = $this->weekSessionDates[$week][$session] ?? null;

        return is_string($date)
            && $date !== ''
            && AthleteDashboardDate::canRecordProgramExercisesForDate($date);
    }

    protected function clearPendingRecordAction(): void
    {
        $this->pendingRecordAction = null;
        $this->pendingRecordWeek = null;
        $this->pendingRecordSession = null;
    }

    #[On('training-session-record-updated')]
    public function refreshRecordedSession(int $trainingProgramId, int $programExerciseId): void
    {
        if ($this->scheduledTrainingProgramId !== $trainingProgramId
            || ($programExerciseId !== 0 && $this->programExerciseId !== $programExerciseId)) {
            return;
        }

        $this->refreshRecordedSessionData();
    }

    protected function refreshRecordedSessionData(): void
    {
        $this->bumpGridRenderVersion();
        unset(
            $this->actualCellValues,
            $this->actualSessionValues,
            $this->editableActualSessionsByWeek,
            $this->slotExercisesByWeekSession,
            $this->snapshotExercisesByWeekSession,
            $this->scheduledSlotsByDate,
            $this->scheduledSnapshotsByDate,
            $this->planActualGridTable
        );
        $this->loadedScheduledSlotsByDate = null;
        $this->loadedScheduledSnapshotsByDate = null;
        $this->forgetSharedScheduledDataCache();
        $this->syncExerciseSessionLocks();
    }

    protected function syncExerciseSessionLocks(): void
    {
        if ($this->scheduledTrainingProgramId === null || $this->userId === null) {
            return;
        }

        $guard = app(TrainingSessionEditGuard::class);

        foreach ($this->weekSessionDates as $weekIndex => $datesForWeek) {
            foreach (array_keys($datesForWeek) as $sessionIndex) {
                $exercise = $this->slotExerciseForWeekSession((int) $weekIndex, (int) $sessionIndex);

                if (! $exercise instanceof TrainingProgramSlotExercise) {
                    continue;
                }

                $this->lockedSessionsByWeek[$weekIndex][$sessionIndex] = $guard
                    ->aggregateColumnsIndicateRecordedExerciseOutcome($exercise);
            }
        }
    }

    protected function previewSlotIdForWeekSession(int $week, int $session): ?int
    {
        $date = $this->weekSessionDates[$week][$session] ?? null;

        if (! is_string($date) || $date === '') {
            return null;
        }

        return TrainingProgramSlot::query()
            ->where('training_program_id', $this->scheduledTrainingProgramId)
            ->where('user_id', $this->userId)
            ->whereDate('datetime', $date)
            ->orderBy('datetime')
            ->orderBy('id')
            ->value('id');
    }

    public function updatedValueDisplayMode(): void
    {
        unset(
            $this->recordMenuOptions,
            $this->actualCellValues,
            $this->actualSessionValues,
            $this->editableActualSessionsByWeek,
            $this->slotExercisesByWeekSession,
            $this->snapshotExercisesByWeekSession,
            $this->scheduledSlotsByDate,
            $this->scheduledSnapshotsByDate,
            $this->planActualGridTable
        );
        $this->loadedScheduledSlotsByDate = null;
        $this->loadedScheduledSnapshotsByDate = null;
        $this->forgetSharedScheduledDataCache();
    }

    #[Computed]
    public function planConfig()
    {
        return PlanGridProfiler::measure('PlanExerciseGrid.planConfig', $this->profileContext(), function () {
            if ($this->planConfigArray === []) {
                $this->planConfigArray = ExerciseProgram::query()->findOrFail($this->planId)->config->toArray();
            }

            return ExercisePlanConfig::from($this->planConfigArray);
        });
    }

    protected function getPlanConfig()
    {
        return $this->planConfig;
    }

    private function resolveScheduledTrainingProgramId(int $planId): ?int
    {
        $program = ExerciseProgram::query()
            ->select(['id', 'parent_type', 'parent_id'])
            ->find($planId);

        if ($program?->parent_type === TrainingProgram::class && $program->parent_id !== null) {
            $exists = TrainingProgram::query()
                ->whereKey($program->parent_id)
                ->exists();

            if ($exists) {
                return (int) $program->parent_id;
            }
        }

        $linkedTrainingProgramIds = TrainingProgram::query()
            ->where('exercise_program_id', $planId)
            ->orderBy('id')
            ->pluck('id');

        if ($linkedTrainingProgramIds->count() === 1) {
            return (int) $linkedTrainingProgramIds->first();
        }

        return null;
    }

    protected function getExerciseConfig(): ExerciseConfig
    {
        return ExerciseConfig::from($this->exerciseConfigArray);
    }

    protected function getCurrentOverrides(): ExerciseOverrides
    {
        $stored = $this->getStoredCurrentOverrides();

        if (! $this->usesFixedSessionGroupCoordinates()) {
            return $stored;
        }

        $visible = ExerciseOverrides::from($stored->toArray());
        $visible->gridOverrides = $this->remapGridOverrideCoordinates(
            $visible->gridOverrides,
            $this->fixedSessionCoordinateMaps()['canonicalToVisible'],
        );
        $visible->ignoredPlanGridOverrideSessions = $this->remapIgnoredSessionCoordinates(
            $visible->ignoredPlanGridOverrideSessions,
            $this->fixedSessionCoordinateMaps()['canonicalToVisible'],
        );

        return $visible;
    }

    protected function getStoredCurrentOverrides(): ExerciseOverrides
    {
        return $this->getPlanConfig()->exerciseOverrides($this->programExerciseId, $this->userId);
    }

    protected function getEffectiveStartsAtDate(): ?string
    {
        if ($this->isUnavailableForMissingMetrics) {
            return '9999-12-31';
        }

        return $this->resolvedExerciseOverrides->effectiveStartsAtDate;
    }

    protected function getEffectiveConfig(): array
    {
        return $this->withResolvedPreviewGrouping($this->resolvedExerciseOverrides->effectiveConfig);
    }

    #[On('coach-settings-saved')]
    public function onCoachSettingsSaved(): void
    {
        $this->bumpGridRenderVersion();
        unset(
            $this->configFingerprint,
            $this->previewGrid,
            $this->displayGrid,
            $this->planGridTable,
            $this->effectiveExpandedWeeks,
            $this->settingBadges,
            $this->resolvedExerciseOverrides,
            $this->copyBuckets,
            $this->copyMenuOptions,
            $this->resetMenuOptions,
            $this->groupingBadge
        );
    }

    protected function getBaseGridOverrides(): array
    {
        $base = $this->getExerciseConfig();

        if ($this->userId !== null) {
            $planOverrides = $this->resolvedExerciseOverrides->defaultOverrides;

            if ($this->getStoredCurrentOverrides()->inheritPlanGridOverrides === false) {
                return $base->overrides;
            }

            return EffectiveExerciseConfig::mergeGridOverrides(
                $base->overrides,
                EffectiveExerciseConfig::withoutIgnoredPlanSessions(
                    $planOverrides->gridOverrides,
                    $this->getStoredCurrentOverrides()->ignoredPlanGridOverrideSessions,
                ),
            );
        }

        return $base->overrides;
    }

    protected function getPlanMeasuredData(): WeightProgressionSetting
    {
        return new WeightProgressionSetting(
            measuredReps: $this->planMeasuredReps,
            measuredWeight: $this->planMeasuredWeight,
            targetGoal: $this->planTargetGoal,
        );
    }

    protected function getEffectiveCellDefault(string $field, int $weekIndex, int $setIndex, int $sessionIndex): mixed
    {
        $row = collect($this->buildDefaultsGrid()->rows)->firstWhere('field', $field);

        return $row?->getCellValue($weekIndex, $setIndex, $sessionIndex);
    }

    protected function getEffectiveSessionDefault(string $field, int $weekIndex, int $sessionIndex): mixed
    {
        $column = collect($this->buildDefaultsGrid()->weekColumns)->firstWhere('field', $field);

        return $column?->getCellValue($weekIndex, 0, $sessionIndex);
    }

    #[Computed]
    public function isDisabled(): bool
    {
        return $this->resolvedExerciseOverrides->disabled;
    }

    #[Computed]
    public function isDisabledByDefault(): bool
    {
        $config = $this->getPlanConfig();
        $planOverrides = $config->defaultExerciseOverrides($this->programExerciseId);

        return $planOverrides->disabled ?? false;
    }

    public function toggleDisabled(): void
    {
        $overrides = $this->getCurrentOverrides();

        if ($this->userId !== null) {
            $currentlyDisabled = $this->isDisabled;
            $overrides->disabled = $currentlyDisabled ? false : true;

            $defaultDisabled = $this->isDisabledByDefault;
            if ($overrides->disabled === $defaultDisabled) {
                $overrides->disabled = null;
            }
        } else {
            $overrides->disabled = ! ($overrides->disabled ?? false) ?: null;
        }

        $this->saveOverrides($overrides, snapshotLockedWeeks: false);
        $this->bumpGridRenderVersion();
        unset($this->isDisabled, $this->isDisabledByDefault, $this->configFingerprint, $this->previewGrid, $this->resolvedExerciseOverrides);
    }

    #[Computed]
    public function requiresMeasuredData(): bool
    {
        $effectiveConfig = $this->getEffectiveConfig();

        return in_array('weight', $effectiveConfig['settings'] ?? [])
            && ($effectiveConfig['weight']['mode'] ?? 'manual') === 'automatic';
    }

    #[Computed]
    public function hasMeasuredData(): bool
    {
        return $this->getPlanMeasuredData()->isComplete();
    }

    #[Computed]
    public function missingBlockGoal(): bool
    {
        return $this->requiresMeasuredData && $this->planTargetGoal === null;
    }

    #[Computed]
    public function missingAthleteMeasurement(): bool
    {
        if ($this->userId === null) {
            return false;
        }

        return $this->missingAthleteMetricLabels !== [];
    }

    #[Computed]
    public function missingAthleteMetricLabels(): array
    {
        if ($this->userId === null) {
            return [];
        }

        return app(ExerciseMetricAvailability::class)->missingRequiredMetricLabels(
            effectiveConfig: $this->getEffectiveConfig(),
            weightProgression: $this->getPlanMeasuredData(),
            maxHR: $this->planMaxHR,
            iatPercent: $this->planIatPercent,
        );
    }

    #[Computed]
    public function isUnavailableForMissingMetrics(): bool
    {
        return $this->missingAthleteMeasurement;
    }

    /** @return array{label: string, color: string|null, overridden: bool} */
    #[Computed]
    public function groupingBadge(): array
    {
        $grouping = $this->effectiveSessionGrouping();
        $overridden = $this->getCurrentOverrides()->sessionGrouping !== null;

        return [
            'label' => match ($grouping->mode) {
                SessionGroupingMode::None->value => 'Ungrouped',
                SessionGroupingMode::Week->value => 'Grouped By Weeks ('.$grouping->groupSize.')',
                default => 'Grouped By Sessions ('.$grouping->groupSize.')',
            },
            'color' => $overridden ? 'green' : null,
            'overridden' => $overridden,
        ];
    }

    /** @return list<array{label: string, modalField: string, overridden: bool}> */
    #[Computed]
    public function settingBadges(): array
    {
        $config = ExerciseConfig::from($this->getEffectiveConfig());
        $currentOverrides = $this->getCurrentOverrides();

        return collect($config->settings)
            ->filter(fn (string $setting) => $config->{$setting} !== null)
            ->flatMap(function (string $setting) use ($config, $currentOverrides) {
                $overridden = $currentOverrides->hasSettingOverride($setting);

                return collect($config->{$setting}->badges())
                    ->map(fn (array $badge) => array_merge($badge, ['overridden' => $overridden]))
                    ->all();
            })
            ->values()
            ->all();
    }

    #[Computed]
    public function configFingerprint(): string
    {
        return md5(json_encode([
            'effectiveConfig' => $this->getEffectiveConfig(),
            'historicalOverrides' => $this->getHistoricalGridOverrides(),
            'lockedSessionsByWeek' => $this->lockedSessionsByWeek,
        ]));
    }

    #[Computed]
    public function previewGrid(): PreviewGrid
    {
        $span = PlanGridProfiler::start('PlanExerciseGrid.previewGrid', $this->profileContext([
            'weeks' => $this->weeks,
            'sessions_per_week' => $this->sessionsPerWeek,
            'week_session_dates_count' => collect($this->weekSessionDates)->flatten()->count(),
        ]));

        try {
            $effectiveConfig = $this->getEffectiveConfig();
            $measuredData = $this->getPlanMeasuredData();

            $overrides = GridOverrides::fromConfig($effectiveConfig['overrides'] ?? []);
            $historicalOverrides = GridOverrides::fromConfig($this->getHistoricalGridOverrides());

            $config = $this->getPlanConfig();
            $planDefaults = $config->defaultExerciseOverrides($this->programExerciseId);
            $originalPlanOverrides = $planDefaults->baselineGridOverrides ?? ['sessions' => [], 'cells' => []];
            $originalEffective = EffectiveExerciseConfig::mergeGridOverrides(
                $this->getExerciseConfig()->overrides,
                $originalPlanOverrides,
            );
            $diffed = $this->diffGridOverrides(
                $effectiveConfig['overrides'] ?? ['sessions' => [], 'cells' => []],
                $originalEffective,
            );
            $highlightOverrides = GridOverrides::fromConfig($diffed);

            $grid = ExercisePreviewBuilder::build(
                $effectiveConfig,
                $measuredData,
                $this->weeks,
                $overrides,
                $this->effectivePreviewSessionsPerWeek($effectiveConfig),
                $highlightOverrides,
                $this->planMaxHR,
                $this->planIatPercent,
                $this->getEffectiveStartsAtDate(),
                $this->weekSessionDates,
                $this->lockedSessionsByWeek,
                $historicalOverrides,
                $this->resolvedWeekSessionCounts(),
                $this->getExerciseConfig()->toArray(),
                $this->resolvedExerciseOverrides->defaultOverrides,
                $this->resolvedExerciseOverrides->userOverrides,
                ! $this->isUnavailableForMissingMetrics,
                $this->usesFixedSessionGroupCoordinates(),
            );

            return $this->clearLockedWeekHighlights($grid);
        } finally {
            PlanGridProfiler::end($span);
        }
    }

    #[Computed]
    public function displayGrid(): PreviewGrid
    {
        $span = PlanGridProfiler::start('PlanExerciseGrid.displayGrid', $this->profileContext());

        try {
            $grid = $this->previewGrid;
            $expandedWeekLookup = collect($this->effectiveExpandedWeeks)
                ->map(fn (mixed $week): int => (int) $week)
                ->all();
            $effectiveConfig = $this->getEffectiveConfig();
            $usesGroupedSessions = SessionGroupingMode::shouldShowGroupColumn(
                $effectiveConfig['preview']['groupingMode'] ?? null,
                $effectiveConfig['preview']['groupSize'] ?? null,
                0,
            );
            $renderGroupedColumn = false;
            $grouping = SessionGroupBuilder::build(
                weekCount: $grid->weekCount,
                sessionCounts: $grid->weekSessionCounts,
                groupingMode: (string) ($effectiveConfig['preview']['groupingMode'] ?? SessionGroupingMode::defaultMode()),
                groupSize: (int) ($effectiveConfig['preview']['groupSize'] ?? SessionGroupingMode::defaultGroupSize()),
                labels: $this->weekLabels,
                expandedIndexes: $expandedWeekLookup,
                lockedSessionsByWeek: $this->lockedSessionsByWeek,
                sessionLabels: $this->sessionLabels,
            );

            $grid->groups = $grouping['groups'];
            $this->applySessionStatusesToGroups($grid->groups);
            $forcedExpandedIndexes = $this->forcedExpandedGroupIndexes($grid, $grid->groups);
            $recordEditingMode = $this->showsRecordEditingMode();

            foreach ($grid->groups as $group) {
                $group->forceExpanded = $recordEditingMode
                    || in_array($group->index, $forcedExpandedIndexes, true);
                $group->collapsible = $usesGroupedSessions && $group->sessionCount > 1 && ! $group->forceExpanded;
                $group->expanded = in_array($group->index, $expandedWeekLookup, true) || $group->forceExpanded;
            }
            $grid->groupColumnLabel = $grouping['columnLabel'];
            $grid->showGroupColumn = $usesGroupedSessions;
            $grid->renderGroupColumn = $renderGroupedColumn;
            $grid->weeks = $grouping['groups'];
            $grid->showWeekColumn = $usesGroupedSessions;
            $grid->showSessionColumn = true;
            $grid->showSessionDates = $this->coachShowsDatePerSession();
            $grid->sessionDateLabels = $this->sessionDateLabels();
            if ($grid->showSessionDates) {
                foreach ($grid->groups as $group) {
                    $scheduledSessions = collect($group->sessions ?? [])
                        ->reject(fn ($session): bool => ($session->status['value'] ?? null) === 'unscheduled');
                    $dateRanges = $scheduledSessions
                        ->map(fn ($session): ?array => $this->weekSessionDateRanges[$session->weekIndex][$session->sessionIndex] ?? null)
                        ->filter(fn (mixed $range): bool => is_array($range) && filled($range['start'] ?? null) && filled($range['end'] ?? null));

                    $group->collapsedMetaLines = $dateRanges->isNotEmpty()
                        ? [$this->formatConciseDateRange(
                            (string) $dateRanges->min('start'),
                            (string) $dateRanges->max('end'),
                        )]
                        : $scheduledSessions
                            ->map(fn ($session): ?string => $grid->sessionDateLabels[$session->weekIndex][$session->sessionIndex] ?? null)
                            ->filter(fn (?string $label): bool => filled($label))
                            ->values()
                            ->all();
                }
            }
            $grid->showCopyMenu = true;
            $grid->autoCopyValuesAutomatically = false;

            return $grid;
        } finally {
            PlanGridProfiler::end($span, [
                'groups' => isset($grid) ? count($grid->groups ?? []) : null,
                'rows' => isset($grid) ? count($grid->rows ?? []) : null,
                'week_columns' => isset($grid) ? count($grid->weekColumns ?? []) : null,
            ]);
        }
    }

    protected function applySessionStatusesToGroups(array $groups): void
    {
        if ($this->sessionStatusesByWeek === []) {
            return;
        }

        $presenter = app(SlotStatusPresenter::class);

        foreach ($groups as $group) {
            $statusValues = [];
            $unscheduledStatus = null;

            foreach ($group->sessions ?? [] as $session) {
                $status = $this->sessionStatusesByWeek[$session->weekIndex][$session->sessionIndex] ?? null;
                if (! is_array($status)) {
                    continue;
                }

                $session->status = $status;

                if (($status['value'] ?? null) === 'unscheduled') {
                    $unscheduledStatus = $status;
                } elseif (filled($status['value'] ?? null)) {
                    $statusValues[] = $status['value'];
                }
            }

            if ($statusValues === []) {
                if (is_array($unscheduledStatus)) {
                    $group->status = $unscheduledStatus;
                }

                continue;
            }

            $value = $presenter->aggregateValue($statusValues);
            $group->status = [
                'value' => $value,
                'label' => $presenter->label($value),
                'color' => $presenter->color($value),
            ];
        }
    }

    #[Computed]
    public function planGridTable(): array
    {
        $span = PlanGridProfiler::start('PlanExerciseGrid.planGridTable', $this->profileContext());

        try {
            $grid = $this->displayGrid;
            $sessionScopedFields = collect($grid->weekColumns)->pluck('field')->all();

            return [
                'mode' => 'planned',
                'showsSettingBadges' => true,
                'usesGrouping' => true,
                'setCount' => $grid->setCount,
                'setLabel' => $grid->setLabel,
                'sessionScopedFields' => $sessionScopedFields,
                'groups' => collect($grid->groups)->map(function ($group) use ($grid): array {
                    $sessions = collect($group->sessions ?? [])
                        ->map(function ($session) use ($grid): array {
                            $week = $session->weekIndex;
                            $sessionIndex = $session->sessionIndex;

                            return [
                                'week' => $week,
                                'session' => $sessionIndex,
                                'sessionNumber' => $session->sessionNumber,
                                'locked' => (bool) ($session->locked ?? false),
                                'setRows' => collect($grid->rows)
                                    ->reject(fn ($row): bool => (bool) ($row->lastSessionOnly ?? false))
                                    ->map(fn ($row): array => [
                                        'field' => $row->field,
                                        'label' => $row->label,
                                        'cells' => collect(range(0, max($grid->setCount - 1, 0)))
                                            ->map(fn (int $set): mixed => $row->getCellValue($week, $set, $sessionIndex))
                                            ->all(),
                                    ])->all(),
                                'sessionRows' => collect($grid->weekColumns)
                                    ->map(fn ($column): array => [
                                        'field' => $column->field,
                                        'label' => $column->label,
                                        'value' => $column->getCellValue($week, 0, $sessionIndex),
                                    ])->all(),
                            ];
                        })
                        ->all();

                    return [
                        'label' => trim(strip_tags((string) ($group->label ?? ''))),
                        'sessionCount' => count($sessions),
                        'expanded' => (bool) ($group->expanded ?? false),
                        'sessions' => $sessions,
                    ];
                })->all(),
            ];
        } finally {
            PlanGridProfiler::end($span);
        }
    }

    #[Computed]
    public function scheduledSlotsByDate(): array
    {
        return $this->resolveScheduledSlotsByDate();
    }

    #[Computed]
    public function scheduledSnapshotsByDate(): array
    {
        return $this->resolveScheduledSnapshotsByDate();
    }

    #[Computed]
    public function slotExercisesByWeekSession(): array
    {
        return PlanGridProfiler::measure('PlanExerciseGrid.slotExercisesByWeekSession', $this->profileContext([
            'week_session_dates_count' => collect($this->weekSessionDates)->flatten()->count(),
            'shows_actual_value_tabs' => $this->showsActualValueTabs,
        ]), function (): array {
            if (! $this->showsActualValueTabs) {
                return [];
            }

            $slotExercises = [];

            foreach ($this->weekSessionDates as $weekIndex => $datesForWeek) {
                foreach (array_keys($datesForWeek) as $sessionIndex) {
                    $slotExercise = $this->slotExerciseForWeekSession($weekIndex, (int) $sessionIndex);

                    if ($slotExercise instanceof TrainingProgramSlotExercise) {
                        $slotExercises[$weekIndex][$sessionIndex] = $slotExercise;
                    }
                }
            }

            return $slotExercises;
        });
    }

    #[Computed]
    public function snapshotExercisesByWeekSession(): array
    {
        return PlanGridProfiler::measure('PlanExerciseGrid.snapshotExercisesByWeekSession', $this->profileContext([
            'week_session_dates_count' => collect($this->weekSessionDates)->flatten()->count(),
        ]), function (): array {
            if ($this->scheduledTrainingProgramId === null || $this->userId === null) {
                return [];
            }

            $snapshotExercises = [];

            foreach ($this->weekSessionDates as $weekIndex => $datesForWeek) {
                foreach (array_keys($datesForWeek) as $sessionIndex) {
                    $snapshotExercise = $this->snapshotExerciseForWeekSession($weekIndex, (int) $sessionIndex);

                    if ($snapshotExercise instanceof ScheduledExerciseSnapshotData) {
                        $snapshotExercises[$weekIndex][$sessionIndex] = $snapshotExercise;
                    }
                }
            }

            return $snapshotExercises;
        });
    }

    #[Computed]
    public function planActualGridTable(): array
    {
        $span = PlanGridProfiler::start('PlanExerciseGrid.planActualGridTable', $this->profileContext([
            'shows_actual_value_tabs' => $this->showsActualValueTabs,
            'value_display_mode' => $this->valueDisplayMode,
        ]));

        try {
            if (! $this->showsActualValueTabs || $this->valueDisplayMode !== 'actual') {
                return [];
            }

            $previewGrid = $this->previewGrid;
            $displayRows = array_merge(
                array_values(array_filter(
                    $previewGrid->rows,
                    fn ($row): bool => ! in_array(($row->field ?? null), ['oneRepMax', 'sets'], true)
                )),
                array_values(array_filter(
                    $previewGrid->weekColumns,
                    fn ($column): bool => ($column->field ?? null) !== 'sets'
                )),
            );
            $sessionScopedFields = collect($previewGrid->weekColumns)->pluck('field')->flip();
            $snapshotExercises = $this->snapshotExercisesByWeekSession;
            $sessions = [];
            $blockSessionNumber = 1;

            foreach ($this->resolvedWeekSessionCounts() as $weekIndex => $sessionCount) {
                for ($sessionIndex = 0; $sessionIndex < $sessionCount; $sessionIndex++) {
                    $snapshotExercise = $snapshotExercises[$weekIndex][$sessionIndex] ?? null;
                    $slotExercise = $this->slotExercisesByWeekSession[$weekIndex][$sessionIndex] ?? null;
                    $rows = [];

                    foreach ($displayRows as $row) {
                        $isSessionScoped = $sessionScopedFields->has($row->field);
                        $cells = [];

                        for ($setIndex = 0; $setIndex < $previewGrid->setCount; $setIndex++) {
                            $plannedValue = $this->resolvePlanActualPlannedCellValue(
                                $row,
                                $weekIndex,
                                $sessionIndex,
                                $setIndex,
                                $snapshotExercise,
                                $isSessionScoped,
                            );
                            $actualValue = $this->resolvePlanActualActualCellValue(
                                $row->field,
                                $sessionIndex,
                                $setIndex,
                                $snapshotExercise,
                            );
                            $actualHighlighted = $actualValue !== null
                                && $actualValue !== '-'
                                && (string) $actualValue !== (string) $plannedValue;
                            $cells[] = [
                                'set' => $setIndex,
                                'planned' => $plannedValue,
                                'actual' => $actualValue,
                                'plannedEditable' => $this->canEditPlanActualPlannedCell($row->field, $plannedValue, $slotExercise),
                                'actualEditable' => $this->canEditPlanActualActualCell($row->field, $actualValue, $slotExercise, $weekIndex, $sessionIndex),
                                'actualHighlighted' => $actualHighlighted,
                                'actualColor' => $actualHighlighted
                                    ? $row->resolveCellColor($weekIndex, $isSessionScoped ? null : $setIndex, true, $sessionIndex)
                                    : $row->resolveCellColor($weekIndex, $isSessionScoped ? null : $setIndex, false, $sessionIndex),
                            ];
                        }

                        $rows[] = [
                            'field' => $row->field,
                            'label' => $row->label,
                            'color' => $row->color,
                            'inputMeta' => $row->inputMeta,
                            'cells' => $cells,
                        ];
                    }

                    $sessions[] = [
                        'week' => $weekIndex,
                        'session' => $sessionIndex,
                        'sessionNumber' => $blockSessionNumber,
                        'sessionDateLabel' => $this->sessionDateLabels()[$weekIndex][$sessionIndex] ?? null,
                        'locked' => $this->isSessionLocked($weekIndex, $sessionIndex),
                        'recordable' => $this->canManageSessionRecords()
                            && $this->canRecordWeekSession($weekIndex, $sessionIndex),
                        'status' => $slotExercise?->status?->value ?? 'pending',
                        'statusLabel' => $slotExercise?->status?->label() ?? __('Pending'),
                        'statusColor' => match ($slotExercise?->status?->value) {
                            'completed' => 'green',
                            'partially_completed' => 'amber',
                            'skipped' => 'sky',
                            default => 'zinc',
                        },
                        'rows' => $rows,
                    ];
                    $blockSessionNumber++;
                }
            }

            return [
                'mode' => 'actual',
                'showsSettingBadges' => false,
                'usesGrouping' => false,
                'showSessionDates' => $this->coachShowsDatePerSession(),
                'setCount' => $previewGrid->setCount,
                'setLabel' => $previewGrid->setLabel,
                'sessions' => $sessions,
            ];
        } finally {
            PlanGridProfiler::end($span, [
                'sessions' => isset($sessions) ? count($sessions) : null,
                'display_rows' => isset($displayRows) ? count($displayRows) : null,
            ]);
        }
    }

    #[Computed]
    public function showsActualValueTabs(): bool
    {
        return $this->showActualValueTabs
            && $this->userId !== null
            && $this->scheduledTrainingProgramId !== null;
    }

    #[Computed]
    public function showsPlannedActualSetColumns(): bool
    {
        return $this->showsActualValueTabs
            && ($this->valueDisplayMode === 'actual' || $this->showPlannedActualInline);
    }

    protected function showsRecordEditingMode(): bool
    {
        return $this->showsActualValueTabs
            && ($this->valueDisplayMode === 'actual' || $this->showPlannedActualInline);
    }

    public function togglePlannedActualInline(): void
    {
        abort_unless($this->showsActualValueTabs, 403);

        $this->showPlannedActualInline = ! $this->showPlannedActualInline;
    }

    /** @return array<string, array<int, array<int, array<int, string>>>> */
    #[Computed]
    public function actualCellValues(): array
    {
        return $this->buildActualValueMaps()['cells'];
    }

    /** @return array<string, array<int, array<int, string>>> */
    #[Computed]
    public function actualSessionValues(): array
    {
        return $this->buildActualValueMaps()['sessions'];
    }

    /** @return array<int, array<int, bool>> */
    #[Computed]
    public function editableActualSessionsByWeek(): array
    {
        return $this->buildActualValueMaps()['editable'];
    }

    /** @return array{cells: array, weeks: array} */
    protected function getHistoricalGridOverrides(): array
    {
        $resolvedOverrides = $this->resolvedExerciseOverrides;
        $planOverrides = $resolvedOverrides->defaultOverrides;
        $historicalOverrides = EffectiveExerciseConfig::mergeGridOverrides(
            $this->materializedHistoricalGridOverrides(),
            $planOverrides->historicalGridOverrides,
        );

        if ($resolvedOverrides->userOverrides !== null) {
            $historicalOverrides = EffectiveExerciseConfig::mergeGridOverrides(
                $historicalOverrides,
                $resolvedOverrides->userOverrides->historicalGridOverrides,
            );
        }

        return $historicalOverrides;
    }

    /** @return array{sessions: array, cells: array} */
    protected function materializedHistoricalGridOverrides(): array
    {
        if ($this->scheduledTrainingProgramId === null || $this->userId === null) {
            return OverrideManager::reset();
        }

        $historicalOverrides = OverrideManager::reset();

        foreach ($this->weekSessionDates as $weekIndex => $datesForWeek) {
            foreach (array_keys($datesForWeek) as $sessionIndex) {
                if (! ($this->lockedSessionsByWeek[$weekIndex][$sessionIndex] ?? false)) {
                    continue;
                }

                $snapshotExercise = $this->snapshotExercisesByWeekSession[$weekIndex][$sessionIndex] ?? null;

                if (! $snapshotExercise instanceof ScheduledExerciseSnapshotData) {
                    continue;
                }

                $historicalOverrides = $this->putSessionOverride(
                    $historicalOverrides,
                    $weekIndex,
                    (int) $sessionIndex,
                    'sets',
                    count($snapshotExercise->sets),
                );

                foreach ($snapshotExercise->sets as $set) {
                    $setIndex = max(((int) $set->setNumber) - 1, 0);

                    foreach ($set->values as $valueRow) {
                        $plannedValue = $valueRow->plannedValue;

                        if ($plannedValue === null || $plannedValue === '' || $plannedValue === '-' || $plannedValue === '—') {
                            continue;
                        }

                        if ($this->settingAppliesPerSession($valueRow->settingKey)) {
                            $historicalOverrides = $this->putSessionOverride(
                                $historicalOverrides,
                                $weekIndex,
                                (int) $sessionIndex,
                                $valueRow->settingKey,
                                $plannedValue,
                            );

                            continue;
                        }

                        $historicalOverrides = $this->putCellOverride(
                            $historicalOverrides,
                            $weekIndex,
                            (int) $sessionIndex,
                            $setIndex,
                            $valueRow->settingKey,
                            $plannedValue,
                        );
                    }
                }
            }
        }

        return $historicalOverrides;
    }

    #[Computed]
    public function resolvedExerciseOverrides(): ResolvedExerciseOverrides
    {
        return PlanGridProfiler::measure('PlanExerciseGrid.resolvedExerciseOverrides', $this->profileContext(), function (): ResolvedExerciseOverrides {
            return $this->getPlanConfig()->resolveExercise($this->getExerciseConfig(), $this->programExerciseId, $this->userId);
        });
    }

    /** @return array{instructions: ?string, videoUrl: ?string, photoUrls: array<int, string>} */
    #[Computed]
    public function exerciseContent(): array
    {
        return [
            'instructions' => $this->resolvedExerciseOverrides->effectiveInstructions ?? $this->baseExerciseInstructions(),
            'videoUrl' => $this->resolvedExerciseOverrides->effectiveVideoUrl ?? $this->baseExerciseVideoUrl(),
            'photoUrls' => $this->baseExercisePhotoUrls(),
        ];
    }

    protected function resolveExerciseOverrides(): ResolvedExerciseOverrides
    {
        return $this->resolvedExerciseOverrides;
    }

    #[Computed]
    public function effectiveExpandedWeeks(): array
    {
        $preview = $this->getEffectiveConfig()['preview'] ?? [];
        $groupingMode = (string) ($preview['groupingMode'] ?? SessionGroupingMode::defaultMode());
        $groupSize = SessionGroupingMode::normalizeGroupSize(
            (int) ($preview['groupSize'] ?? SessionGroupingMode::defaultGroupSize()),
            $groupingMode,
        );
        $groups = SessionGroupBuilder::build(
            weekCount: $this->previewGrid->weekCount,
            sessionCounts: $this->previewGrid->weekSessionCounts,
            groupingMode: $groupingMode,
            groupSize: $groupSize,
            lockedSessionsByWeek: $this->lockedSessionsByWeek,
            sessionLabels: $this->sessionLabels,
        )['groups'];

        $manualExpanded = collect($this->expandedWeeks)
            ->map(fn (mixed $index): int => (int) $index)
            ->values()
            ->all();

        return collect(array_merge(
            $manualExpanded,
            $this->forcedExpandedGroupIndexes($this->previewGrid, $groups),
        ))->unique()->values()->all();
    }

    public function toggleExpandedGroup(int $groupIndex): void
    {
        if (in_array($groupIndex, $this->forcedExpandedGroupIndexes($this->previewGrid, $this->displayGrid->groups), true)) {
            return;
        }

        $expanded = collect($this->expandedWeeks)
            ->map(fn (mixed $index): int => (int) $index)
            ->values()
            ->all();

        if (in_array($groupIndex, $expanded, true)) {
            $expanded = array_values(array_filter($expanded, fn (int $index): bool => $index !== $groupIndex));
        } else {
            $expanded[] = $groupIndex;
        }

        $this->expandedWeeks = array_values(array_unique($expanded));

        unset($this->displayGrid, $this->effectiveExpandedWeeks, $this->copyBuckets, $this->copyMenuOptions, $this->resetMenuOptions);
    }

    protected function withResolvedPreviewGrouping(array $config): array
    {
        $preview = $config['preview'] ?? [];
        $grouping = $this->resolveDefaultPreviewGrouping();
        $preview['groupingMode'] ??= $grouping['mode'];
        $preview['groupSize'] ??= $grouping['groupSize'];
        $preview['copyValuesAutomatically'] ??= $grouping['copyValuesAutomatically'];

        $config['preview'] = $preview;

        return $config;
    }

    protected function coachSessionGroupingSetting(): SessionGroupingSetting
    {
        $user = Auth::user();

        return SessionGroupingSetting::from($user?->config->get(
            'settings.'.SessionGroupingSetting::fieldsetKey(),
            []
        ) ?? []);
    }

    protected function coachShowsDatePerSession(): bool
    {
        return (bool) ($this->coachSessionGroupingSetting()->showDatePerSession ?? false);
    }

    /** @return array<int, array<int, string>> */
    protected function sessionDateLabels(): array
    {
        $labels = [];

        foreach ($this->weekSessionDates as $weekIndex => $datesForWeek) {
            foreach ($datesForWeek as $sessionIndex => $date) {
                $range = $this->weekSessionDateRanges[$weekIndex][$sessionIndex] ?? null;
                if (is_array($range) && filled($range['start'] ?? null) && filled($range['end'] ?? null)) {
                    $labels[$weekIndex][$sessionIndex] = $this->formatConciseDateRange(
                        (string) $range['start'],
                        (string) $range['end'],
                    );

                    continue;
                }

                if (! is_string($date) || $date === '') {
                    continue;
                }

                $labels[$weekIndex][$sessionIndex] = Carbon::parse($date)->format('d.m.y');
            }
        }

        return $labels;
    }

    protected function formatConciseDateRange(string $start, string $end): string
    {
        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($end);

        if ($startDate->isSameDay($endDate)) {
            return $startDate->format('j.n');
        }

        if ($startDate->year === $endDate->year) {
            return $startDate->format('j.n').' - '.$endDate->format('j.n');
        }

        return $startDate->format('j.n.y').' - '.$endDate->format('j.n.y');
    }

    /**
     * @return array{mode: string, groupSize: int, copyValuesAutomatically: bool}
     */
    protected function resolveDefaultPreviewGrouping(): array
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
        ])->toArray();
    }

    /** @return array{sessions: array, cells: array} */
    protected function diffGridOverrides(array $current, ?array $baseline): array
    {
        if ($baseline === null) {
            return $current;
        }

        $baselineSessionKeys = [];
        foreach ($baseline['sessions'] ?? [] as $session) {
            $baselineSessionKeys[$session['week'].'-'.$session['session']] = $session['data'] ?? [];
        }

        $baselineCellKeys = [];
        foreach ($baseline['cells'] ?? [] as $cell) {
            $key = $cell['week'].'-'.($cell['session'] ?? 0).'-'.$cell['set'];
            $baselineCellKeys[$key] = $cell['data'] ?? [];
        }

        $diffSessions = [];
        foreach ($current['sessions'] ?? [] as $session) {
            $key = $session['week'].'-'.$session['session'];
            if (! isset($baselineSessionKeys[$key])) {
                $diffSessions[] = $session;
            } else {
                $newData = array_diff_assoc($session['data'] ?? [], $baselineSessionKeys[$key]);
                if (! empty($newData)) {
                    $diffSessions[] = array_merge($session, ['data' => $newData]);
                }
            }
        }

        $diffCells = [];
        foreach ($current['cells'] ?? [] as $cell) {
            $key = $cell['week'].'-'.($cell['session'] ?? 0).'-'.$cell['set'];
            if (! isset($baselineCellKeys[$key])) {
                $diffCells[] = $cell;
            } else {
                $newData = array_diff_assoc($cell['data'] ?? [], $baselineCellKeys[$key]);
                if (! empty($newData)) {
                    $diffCells[] = array_merge($cell, ['data' => $newData]);
                }
            }
        }

        return ['sessions' => $diffSessions, 'cells' => $diffCells];
    }

    protected function clearLockedWeekHighlights(PreviewGrid $grid): PreviewGrid
    {
        $lockedWeeks = collect($this->lockedSessionsByWeek)
            ->map(fn (array $sessions): bool => in_array(true, $sessions, true))
            ->all();

        foreach ($grid->rows as $row) {
            foreach ($row->overrides as $week => $weekOverrides) {
                if (! ($lockedWeeks[$week] ?? false)) {
                    continue;
                }

                if (is_array($weekOverrides)) {
                    foreach (array_keys($weekOverrides) as $set) {
                        $row->overrides[$week][$set] = false;
                    }

                    continue;
                }

                $row->overrides[$week] = false;
            }

            foreach ($row->sessionOverrides as $week => $sessionOverrides) {
                foreach ($sessionOverrides as $session => $setOverrides) {
                    if (! (($this->lockedSessionsByWeek[$week][$session] ?? false))) {
                        continue;
                    }

                    foreach (array_keys($setOverrides) as $set) {
                        $row->sessionOverrides[$week][$session][$set] = false;
                    }
                }
            }
        }

        foreach ($grid->weekColumns as $column) {
            foreach ($column->sessionOverrides as $week => $sessionOverrides) {
                foreach ($sessionOverrides as $session => $setOverrides) {
                    if (! (($this->lockedSessionsByWeek[$week][$session] ?? false))) {
                        continue;
                    }

                    foreach (array_keys($setOverrides) as $set) {
                        $column->sessionOverrides[$week][$session][$set] = false;
                    }
                }
            }
        }

        return $grid;
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

    /** @return array{cells: array<string, array<int, array<int, array<int, string>>>>, sessions: array<string, array<int, array<int, string>>>, editable: array<int, array<int, bool>>} */
    protected function buildActualValueMaps(): array
    {
        if (! $this->showsActualValueTabs) {
            return ['cells' => [], 'sessions' => [], 'editable' => []];
        }

        $cellValues = [];
        $sessionValues = [];
        $editableSessions = [];
        $snapshotExercises = $this->snapshotExercisesByWeekSession;

        foreach ($this->weekSessionDates as $weekIndex => $datesForWeek) {
            foreach ($datesForWeek as $sessionIndex => $date) {
                $snapshotExercise = $snapshotExercises[$weekIndex][$sessionIndex] ?? null;

                if (! $snapshotExercise instanceof ScheduledExerciseSnapshotData) {
                    continue;
                }

                $editableSessions[$weekIndex][$sessionIndex] = $this->valueDisplayMode === 'actual';

                foreach ($snapshotExercise->sets as $set) {
                    $setIndex = max(((int) $set->setNumber) - 1, 0);

                    if ($this->snapshotSetHasStatus($set, TrainingProgramSlotSetStatusEnum::Skipped)) {
                        foreach ($set->values as $valueRow) {
                            $cellValues[$valueRow->settingKey][$weekIndex][$sessionIndex][$setIndex] = __('Skipped');
                        }

                        continue;
                    }

                    foreach ($set->values as $valueRow) {
                        $formatted = $this->formatSnapshotActualDisplayValue($set, $valueRow);

                        if ($formatted === null) {
                            continue;
                        }

                        $cellValues[$valueRow->settingKey][$weekIndex][$sessionIndex][$setIndex] = $formatted;
                    }
                }

                foreach ($this->previewGrid->weekColumns as $column) {
                    $sessionValues[$column->field][$weekIndex][$sessionIndex] = $this->resolveActualSnapshotSessionValue(
                        $snapshotExercise,
                        $column->field,
                    ) ?? '-';
                }
            }
        }

        return ['cells' => $cellValues, 'sessions' => $sessionValues, 'editable' => $editableSessions];
    }

    public function updateActualCellValue(int $weekIndex, int $setIndex, string $field, mixed $value, int $session): void
    {
        abort_unless($this->canEditActualsForSession($weekIndex, $session), 403);

        $slotExercise = $this->slotExerciseForWeekSession($weekIndex, $session);
        abort_unless($slotExercise instanceof TrainingProgramSlotExercise, 404);

        $set = $slotExercise->sets
            ->sortBy('set_number')
            ->values()
            ->get($setIndex);

        abort_unless($set !== null, 404);

        app(AthleteExerciseValueService::class)->saveExerciseValues($slotExercise, [
            $set->id => [$field => $value],
        ], onlyProvided: true);

        $this->bumpGridRenderVersion();
        unset(
            $this->actualCellValues,
            $this->actualSessionValues,
            $this->editableActualSessionsByWeek,
            $this->slotExercisesByWeekSession,
            $this->snapshotExercisesByWeekSession,
            $this->scheduledSlotsByDate,
            $this->scheduledSnapshotsByDate,
            $this->planActualGridTable
        );
        $this->loadedScheduledSlotsByDate = null;
        $this->loadedScheduledSnapshotsByDate = null;
        $this->forgetSharedScheduledDataCache();
    }

    public function updateActualSessionValue(int $weekIndex, int $session, string $field, mixed $value): void
    {
        abort_unless($this->canEditActualsForSession($weekIndex, $session), 403);

        if ($field === 'sets') {
            return;
        }

        $slotExercise = $this->slotExerciseForWeekSession($weekIndex, $session);
        abort_unless($slotExercise instanceof TrainingProgramSlotExercise, 404);

        $payload = $slotExercise->sets
            ->mapWithKeys(fn ($set): array => [$set->id => [$field => $value]])
            ->all();

        app(AthleteExerciseValueService::class)->saveExerciseValues($slotExercise, $payload, onlyProvided: true);

        $this->bumpGridRenderVersion();
        unset(
            $this->actualCellValues,
            $this->actualSessionValues,
            $this->editableActualSessionsByWeek,
            $this->slotExercisesByWeekSession,
            $this->snapshotExercisesByWeekSession,
            $this->scheduledSlotsByDate,
            $this->scheduledSnapshotsByDate,
            $this->planActualGridTable
        );
        $this->loadedScheduledSlotsByDate = null;
        $this->loadedScheduledSnapshotsByDate = null;
        $this->forgetSharedScheduledDataCache();
    }

    public function updatePlannedDisplayCellValue(int $weekIndex, int $setIndex, string $field, mixed $value, int $session): void
    {
        abort_unless($this->valueDisplayMode === 'actual' && $this->showsActualValueTabs, 403);
        abort_unless($field !== 'sets', 403);

        if ($this->isSessionLocked($weekIndex, $session)) {
            $this->updateHistoricalPlannedDisplayValue($weekIndex, $setIndex, $field, $value, $session);

            return;
        }

        $slotExercise = $this->slotExerciseForWeekSession($weekIndex, $session);
        abort_unless($slotExercise instanceof TrainingProgramSlotExercise, 404);

        $set = $slotExercise->sets
            ->sortBy('set_number')
            ->values()
            ->get($setIndex);

        abort_unless($set instanceof TrainingProgramSlotSet, 404);

        app(TrainingSessionPlannedValueService::class)->saveExercisePlannedValues($slotExercise, [
            $set->id => [$field => $value],
        ], onlyProvided: true);

        $this->bumpGridRenderVersion();
        unset(
            $this->slotExercisesByWeekSession,
            $this->snapshotExercisesByWeekSession,
            $this->scheduledSlotsByDate,
            $this->scheduledSnapshotsByDate,
            $this->actualCellValues,
            $this->actualSessionValues,
            $this->editableActualSessionsByWeek,
            $this->planActualGridTable
        );
        $this->loadedScheduledSlotsByDate = null;
        $this->loadedScheduledSnapshotsByDate = null;
    }

    protected function updateHistoricalPlannedDisplayValue(int $weekIndex, int $setIndex, string $field, mixed $value, int $session): void
    {
        $targets = $this->lockedHistoricalTargetsForSession($weekIndex, $session);
        abort_unless($targets !== [], 404);

        $overrides = $this->getCurrentOverrides();
        $historicalGridOverrides = $overrides->historicalGridOverrides;

        foreach ($targets as $target) {
            if ($this->settingAppliesPerSession($field)) {
                $historicalGridOverrides = $this->putSessionOverride(
                    $historicalGridOverrides,
                    (int) $target['week'],
                    (int) $target['session'],
                    $field,
                    $value,
                );

                continue;
            }

            $historicalGridOverrides = $this->putCellOverride(
                $historicalGridOverrides,
                (int) $target['week'],
                (int) $target['session'],
                $setIndex,
                $field,
                $value,
            );
        }

        $overrides->historicalGridOverrides = $historicalGridOverrides;

        $this->saveOverrides($overrides, notifyParent: false, snapshotLockedWeeks: false);

        $this->bumpGridRenderVersion();
        unset(
            $this->slotExercisesByWeekSession,
            $this->snapshotExercisesByWeekSession,
            $this->scheduledSlotsByDate,
            $this->scheduledSnapshotsByDate,
            $this->actualCellValues,
            $this->actualSessionValues,
            $this->editableActualSessionsByWeek,
            $this->planActualGridTable
        );
        $this->loadedScheduledSlotsByDate = null;
        $this->loadedScheduledSnapshotsByDate = null;
    }

    protected function matchingSlotExercise(TrainingProgramSlot $slot): ?TrainingProgramSlotExercise
    {
        return $slot->exercises->first(
            fn (TrainingProgramSlotExercise $slotExercise): bool => (int) ($slotExercise->exercise_program_exercise_id ?? 0) === $this->programExerciseId,
        );
    }

    protected function matchingSnapshotExercise(array $exercises): ?ScheduledExerciseSnapshotData
    {
        return collect($exercises)->first(
            fn (ScheduledExerciseSnapshotData $exercise): bool => (int) ($exercise->programExerciseId ?? 0) === $this->programExerciseId,
        );
    }

    protected function slotExerciseForWeekSession(int $weekIndex, int $sessionIndex): ?TrainingProgramSlotExercise
    {
        $date = $this->resolvedScheduledSessionDate($weekIndex, $sessionIndex);

        if ($date === null) {
            return null;
        }

        $slot = $this->resolveScheduledSlotsByDate()[$date] ?? null;

        if (! $slot instanceof TrainingProgramSlot) {
            return null;
        }

        return $this->matchingSlotExercise($slot);
    }

    protected function snapshotExerciseForWeekSession(int $weekIndex, int $sessionIndex): ?ScheduledExerciseSnapshotData
    {
        $date = $this->resolvedScheduledSessionDate($weekIndex, $sessionIndex);

        if ($date === null) {
            return null;
        }

        $snapshot = $this->resolveScheduledSnapshotsByDate()[$date] ?? null;

        if (! $snapshot instanceof ScheduledSessionSnapshotData) {
            return null;
        }

        return $this->matchingSnapshotExercise($snapshot->exercises);
    }

    protected function canEditActualsForSession(int $weekIndex, int $sessionIndex): bool
    {
        if ($this->valueDisplayMode !== 'actual' || ! $this->showsActualValueTabs) {
            return false;
        }

        return $this->slotExerciseForWeekSession($weekIndex, $sessionIndex) instanceof TrainingProgramSlotExercise;
    }

    protected function resolveActualSnapshotSessionValue(ScheduledExerciseSnapshotData $snapshotExercise, string $field): ?string
    {
        if ($field === 'sets') {
            return (string) collect($snapshotExercise->sets)
                ->reject(fn (ScheduledSetSnapshotData $set): bool => $this->snapshotSetHasStatus($set, TrainingProgramSlotSetStatusEnum::Skipped))
                ->count();
        }

        if ($snapshotExercise->sets !== [] && collect($snapshotExercise->sets)->every(
            fn (ScheduledSetSnapshotData $set): bool => $this->snapshotSetHasStatus($set, TrainingProgramSlotSetStatusEnum::Skipped)
        )) {
            return __('Skipped');
        }

        $formatted = collect($snapshotExercise->sets)
            ->map(function ($set) use ($field): ?string {
                if ($this->snapshotSetHasStatus($set, TrainingProgramSlotSetStatusEnum::Skipped)) {
                    return __('Skipped');
                }

                $valueRow = collect($set->values)->firstWhere('settingKey', $field);

                return $valueRow instanceof ScheduledValueSnapshotData
                    ? $this->formatSnapshotActualDisplayValue($set, $valueRow)
                    : null;
            })
            ->values();

        if ($formatted->every(fn (?string $value): bool => $value === null)) {
            return null;
        }

        if ($formatted->contains(fn (?string $value): bool => $value === null)) {
            return '-';
        }

        $unique = $formatted->unique()->values();

        return $unique->count() === 1
            ? $unique->first()
            : 'Varies';
    }

    protected function settingAppliesPerSession(string $settingKey): bool
    {
        $config = $this->getEffectiveConfig();
        $settingConfig = $config[$settingKey] ?? [];

        return ApplyPerScope::normalize($settingConfig['applyPer'] ?? null) === ApplyPerScope::SESSION
            || $settingKey === 'sets';
    }

    protected function extractPlannedValue(?TrainingProgramSlotSetValue $valueRow): mixed
    {
        if (! $valueRow || $valueRow->planned_value_type === null) {
            return null;
        }

        return match ($valueRow->planned_value_type) {
            'int' => $valueRow->planned_int_value,
            'decimal' => $valueRow->planned_decimal_value !== null ? (float) $valueRow->planned_decimal_value : null,
            'json' => $valueRow->planned_json_value,
            default => $valueRow->planned_string_value,
        };
    }

    protected function formatPlannedValue(string $field, mixed $value, ?string $unit = null): ?string
    {
        if ($this->isBlankActualValue($value)) {
            return null;
        }

        $settingClass = ExerciseSetting::tryFrom($field)?->settingClass();
        $settingConfig = $this->resolveSettingConfig($field);

        if (is_string($settingClass) && is_subclass_of($settingClass, AbstractSetting::class)) {
            return $settingClass::formatAthleteValue($value, $unit, $settingConfig);
        }

        return match ($field) {
            'duration' => $this->formatDurationActualValue($value, $unit),
            'heartRateZone' => 'Zone '.trim((string) $value),
            default => $this->normalizeActualScalar($value),
        };
    }

    protected function formatActualValue(string $field, mixed $value, ?string $unit = null): ?string
    {
        if ($this->isBlankActualValue($value)) {
            return null;
        }

        $settingClass = ExerciseSetting::tryFrom($field)?->settingClass();
        $settingConfig = $this->resolveSettingConfig($field);

        if (is_string($settingClass) && is_subclass_of($settingClass, AbstractSetting::class)) {
            return $settingClass::formatAthleteValue($value, $unit, $settingConfig);
        }

        return match ($field) {
            'duration' => $this->formatDurationActualValue($value, $unit),
            'heartRateZone' => 'Zone '.trim((string) $value),
            default => $this->normalizeActualScalar($value),
        };
    }

    /** @return array<string, mixed> */
    protected function resolveSettingConfig(string $field): array
    {
        $config = $this->getExerciseConfig()->{$field} ?? null;
        $sets = $this->getExerciseConfig()->sets ?? null;
        $settingConfig = is_object($config) && method_exists($config, 'toArray')
            ? $config->toArray()
            : [];

        $settingConfig['_sets'] = is_object($sets) && method_exists($sets, 'toArray')
            ? $sets->toArray()
            : [];

        return $settingConfig;
    }

    protected function normalizeActualScalar(mixed $value): string
    {
        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_numeric($value) && str_contains((string) $value, '.')) {
            return rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');
        }

        return trim((string) $value);
    }

    protected function formatDurationActualValue(mixed $value, ?string $unit): string
    {
        if ($unit === 'mm:ss' && is_numeric($value)) {
            $totalSeconds = (int) $value;

            return sprintf('%d:%02d', intdiv($totalSeconds, 60), $totalSeconds % 60);
        }

        return $this->normalizeActualScalar($value);
    }

    protected function isBlankActualValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }

    protected function resolvePlanActualPlannedCellValue(mixed $row, int $weekIndex, int $sessionIndex, int $setIndex, ?ScheduledExerciseSnapshotData $snapshotExercise, bool $isSessionScoped): string
    {
        $plannedValue = $isSessionScoped
            ? $row->getCellValue($weekIndex, 0, $sessionIndex)
            : $row->getCellValue($weekIndex, $setIndex, $sessionIndex);

        if ($plannedValue !== null && $plannedValue !== '') {
            return (string) $plannedValue;
        }

        if (! $snapshotExercise instanceof ScheduledExerciseSnapshotData) {
            return '-';
        }

        $set = $snapshotExercise->sets[$setIndex] ?? null;

        if (! $set instanceof ScheduledSetSnapshotData) {
            return '-';
        }

        $valueRow = collect($set->values)->firstWhere('settingKey', $row->field);
        $formatted = $this->formatPlannedValue($row->field, $valueRow?->plannedValue, $valueRow?->unit);

        return $formatted ?? '-';
    }

    protected function resolvePlanActualActualCellValue(string $field, int $sessionIndex, int $setIndex, ?ScheduledExerciseSnapshotData $snapshotExercise): string
    {
        if (! $snapshotExercise instanceof ScheduledExerciseSnapshotData) {
            return '-';
        }

        if ($field === 'sets') {
            return (string) collect($snapshotExercise->sets)
                ->reject(fn (ScheduledSetSnapshotData $set): bool => $this->snapshotSetHasStatus($set, TrainingProgramSlotSetStatusEnum::Skipped))
                ->count();
        }

        $set = $snapshotExercise->sets[$setIndex] ?? null;

        if (! $set instanceof ScheduledSetSnapshotData) {
            return '-';
        }

        if ($this->snapshotSetHasStatus($set, TrainingProgramSlotSetStatusEnum::Skipped)) {
            return __('Skipped');
        }

        $valueRow = collect($set->values)->firstWhere('settingKey', $field);
        $formatted = $valueRow instanceof ScheduledValueSnapshotData
            ? $this->formatSnapshotActualDisplayValue($set, $valueRow)
            : null;

        return $formatted ?? '-';
    }

    protected function formatSnapshotActualDisplayValue(ScheduledSetSnapshotData $set, ScheduledValueSnapshotData $valueRow): ?string
    {
        $formatted = $this->formatActualValue($valueRow->settingKey, $valueRow->actualValue, $valueRow->unit);

        if ($formatted !== null) {
            return $formatted;
        }

        if (! $this->snapshotSetHasStatus($set, TrainingProgramSlotSetStatusEnum::Completed)
            && ! $this->snapshotSetHasStatus($set, TrainingProgramSlotSetStatusEnum::CompletedWithModification)) {
            return null;
        }

        return $this->formatPlannedValue($valueRow->settingKey, $valueRow->plannedValue, $valueRow->unit);
    }

    protected function snapshotSetHasStatus(ScheduledSetSnapshotData $set, TrainingProgramSlotSetStatusEnum $status): bool
    {
        return (string) ($set->status->value ?? $set->status) === $status->value;
    }

    protected function canEditPlanActualPlannedCell(string $field, string $plannedValue, ?TrainingProgramSlotExercise $slotExercise): bool
    {
        return $this->valueDisplayMode === 'actual'
            && $this->showsActualValueTabs
            && $field !== 'sets'
            && $plannedValue !== '-'
            && $slotExercise instanceof TrainingProgramSlotExercise;
    }

    protected function canEditPlanActualActualCell(string $field, string $actualValue, ?TrainingProgramSlotExercise $slotExercise, int $weekIndex, int $sessionIndex): bool
    {
        return $field !== 'sets'
            && $actualValue !== __('Skipped')
            && $slotExercise instanceof TrainingProgramSlotExercise
            && $this->canEditActualsForSession($weekIndex, $sessionIndex);
    }

    public function updateCellOverride(int $weekIndex, int $setIndex, string $field, mixed $value, int $session, bool $applyToAll = false): void
    {
        $span = PlanGridProfiler::start('PlanExerciseGrid.updateCellOverride', $this->profileContext([
            'week' => $weekIndex,
            'set' => $setIndex,
            'field' => $field,
            'session' => $session,
            'apply_to_all' => $applyToAll,
            'value_type' => get_debug_type($value),
        ]));

        try {
            if (! $this->isValidPlanningValue($field, $value, $weekIndex, $setIndex, $session)) {
                return;
            }

            $overrides = $this->getCurrentOverrides();
            $targets = $applyToAll
                ? $this->fanoutTargetsForSession($weekIndex, $session)
                : [['week' => $weekIndex, 'session' => $session]];

            foreach ($targets as $target) {
                $targetWeek = $target['week'];
                $targetSession = $target['session'];

                if ($this->shouldRestrictPlannedEditForSession($targetWeek, $targetSession)) {
                    continue;
                }

                if ($this->isSessionLocked($targetWeek, $targetSession)) {
                    if ($applyToAll) {
                        continue;
                    }

                    $overrides->historicalGridOverrides = OverrideManager::updateCellOverride(
                        $overrides->historicalGridOverrides,
                        $this->getEffectiveConfig(),
                        $this->weeks,
                        $this->sessionsPerWeek,
                        $targetWeek,
                        $setIndex,
                        $field,
                        $value,
                        $targetSession,
                        false,
                        $this->getEffectiveCellDefault($field, $targetWeek, $setIndex, $targetSession),
                        $this->sessionCountForWeek($targetWeek),
                    );

                    continue;
                }

                $overrides->gridOverrides = OverrideManager::updateCellOverride(
                    $overrides->gridOverrides,
                    $this->getEffectiveConfig(),
                    $this->weeks,
                    $this->sessionsPerWeek,
                    $targetWeek,
                    $setIndex,
                    $field,
                    $value,
                    $targetSession,
                    false,
                    $this->getEffectiveCellDefault($field, $targetWeek, $setIndex, $targetSession),
                    $this->sessionCountForWeek($targetWeek),
                );
            }

            $this->saveOverrides(
                $overrides,
                notifyParent: false,
                openRebuildFromDate: $this->earliestOpenRebuildDateForTargets($targets),
            );
            $this->bumpGridRenderVersion();
            unset($this->configFingerprint, $this->previewGrid, $this->displayGrid, $this->planGridTable, $this->resolvedExerciseOverrides, $this->copyBuckets, $this->copyMenuOptions, $this->resetMenuOptions);
        } finally {
            PlanGridProfiler::end($span, [
                'target_count' => isset($targets) ? count($targets) : null,
            ]);
        }
    }

    public function updateSessionOverride(int $weekIndex, int $session, string $field, mixed $value, bool $applyToAll = false): void
    {
        $span = PlanGridProfiler::start('PlanExerciseGrid.updateSessionOverride', $this->profileContext([
            'week' => $weekIndex,
            'field' => $field,
            'session' => $session,
            'apply_to_all' => $applyToAll,
            'value_type' => get_debug_type($value),
        ]));

        try {
            if ($this->missingAthleteMeasurement) {
                return;
            }

            if (! $this->isValidPlanningValue($field, $value, $weekIndex, null, $session)) {
                return;
            }

            $overrides = $this->getCurrentOverrides();
            $targets = $applyToAll
                ? $this->fanoutTargetsForSession($weekIndex, $session)
                : [['week' => $weekIndex, 'session' => $session]];

            foreach ($targets as $target) {
                $targetWeek = $target['week'];
                $targetSession = $target['session'];

                if ($this->shouldRestrictPlannedEditForSession($targetWeek, $targetSession)) {
                    continue;
                }

                if ($this->isSessionLocked($targetWeek, $targetSession)) {
                    if ($applyToAll) {
                        continue;
                    }

                    $overrides->historicalGridOverrides = OverrideManager::updateSessionOverride(
                        $overrides->historicalGridOverrides,
                        $this->getEffectiveConfig(),
                        $targetWeek,
                        $targetSession,
                        $field,
                        $value,
                        $this->getEffectiveSessionDefault($field, $targetWeek, $targetSession),
                    );

                    continue;
                }

                $overrides->gridOverrides = OverrideManager::updateSessionOverride(
                    $overrides->gridOverrides,
                    $this->getEffectiveConfig(),
                    $targetWeek,
                    $targetSession,
                    $field,
                    $value,
                    $this->getEffectiveSessionDefault($field, $targetWeek, $targetSession),
                );
            }

            $this->saveOverrides(
                $overrides,
                notifyParent: false,
                openRebuildFromDate: $this->earliestOpenRebuildDateForTargets($targets),
            );
            $this->bumpGridRenderVersion();
            unset($this->configFingerprint, $this->previewGrid, $this->displayGrid, $this->planGridTable, $this->resolvedExerciseOverrides, $this->copyBuckets, $this->copyMenuOptions, $this->resetMenuOptions);
        } finally {
            PlanGridProfiler::end($span, [
                'target_count' => isset($targets) ? count($targets) : null,
            ]);
        }
    }

    private function isValidPlanningValue(string $field, mixed $value, ?int $weekIndex = null, ?int $setIndex = null, ?int $session = null): bool
    {
        $config = $this->getEffectiveConfig();

        if ($field === 'reps' && ! RepsSetting::isValidPlanningValue($value, $config)) {
            Flux::toast(
                text: DropSet::isEnabled($config)
                    ? __('Drop-set reps must use comma-separated values or a 3x12 style value.')
                    : __('Reps must be a single number or bilateral value while automatic calculations are enabled.'),
                variant: 'danger',
            );

            return false;
        }

        if (! is_string($value) || ! str_contains($value, ',')) {
            return true;
        }

        if (! DropSet::isEnabled($config) || ! in_array($field, ['reps', 'weight', 'duration'], true)) {
            Flux::toast(
                text: __('Comma-separated values are only available for drop-set reps, weight, and duration.'),
                variant: 'danger',
            );

            return false;
        }

        $settingClass = ExerciseSetting::tryFrom($field)?->settingClass();

        if (! is_string($settingClass) || ! is_subclass_of($settingClass, AbstractSetting::class)) {
            return false;
        }

        $fieldConfig = $config[$field] ?? [];
        $fieldConfig['_sets'] = $config['sets'] ?? [];
        $meta = $settingClass::inputMeta($fieldConfig);

        if ($meta->pattern !== null && preg_match('/^'.$meta->pattern.'$/', trim($value))) {
            $expected = $this->expectedDropSetPartCount($weekIndex, $setIndex, $session);
            $actual = DropSet::partCount($field, $value);

            if ($expected !== null && $actual !== null && $actual !== $expected) {
                Flux::toast(
                    text: __('Drop-set values must have :count parts.', ['count' => $expected]),
                    variant: 'danger',
                );

                return false;
            }

            return true;
        }

        Flux::toast(
            text: __('Please enter a valid drop-set value.'),
            variant: 'danger',
        );

        return false;
    }

    private function expectedDropSetPartCount(?int $weekIndex, ?int $setIndex, ?int $session): ?int
    {
        if ($weekIndex !== null && $setIndex !== null) {
            $repsRow = collect($this->displayGrid->rows)->firstWhere('field', 'reps');
            $repsValue = $repsRow?->getCellValue($weekIndex, $setIndex, $session ?? 0);
            $count = DropSet::partCount('reps', $repsValue);

            if ($count !== null) {
                return $count;
            }
        }

        return DropSet::expectedPartCount($this->getEffectiveConfig());
    }

    protected function buildDefaultsGrid(): PreviewGrid
    {
        return PlanGridProfiler::measure('PlanExerciseGrid.buildDefaultsGrid', $this->profileContext(), function (): PreviewGrid {
            $effectiveConfig = $this->getEffectiveConfig();
            $baseOverrides = $this->getBaseGridOverrides();
            $effectiveConfig['overrides'] = $baseOverrides;
            $measuredData = $this->getPlanMeasuredData();
            $userOverrides = ExerciseOverrides::from($this->resolvedExerciseOverrides->userOverrides?->toArray() ?? []);
            $userOverrides->gridOverrides = OverrideManager::reset();
            $userOverrides->historicalGridOverrides = OverrideManager::reset();

            $grid = ExercisePreviewBuilder::build(
                $effectiveConfig,
                $measuredData,
                $this->weeks,
                GridOverrides::fromConfig($baseOverrides),
                $this->effectivePreviewSessionsPerWeek($effectiveConfig),
                null,
                $this->planMaxHR,
                $this->planIatPercent,
                $this->getEffectiveStartsAtDate(),
                $this->weekSessionDates,
                $this->lockedSessionsByWeek,
                null,
                $this->resolvedWeekSessionCounts(),
                $this->getExerciseConfig()->toArray(),
                $this->resolvedExerciseOverrides->defaultOverrides,
                $userOverrides,
                ! $this->isUnavailableForMissingMetrics,
                $this->usesFixedSessionGroupCoordinates(),
            );

            return $grid;
        });
    }

    /** @return array<int, array{week:int, session:int}> */
    protected function fanoutTargetsForSession(int $weekIndex, int $sessionIndex): array
    {
        $effectiveConfig = $this->getEffectiveConfig();
        $preview = $effectiveConfig['preview'] ?? [];
        $strategyMap = SessionGroupBuilder::buildStrategyMap(
            $this->weeks,
            $this->resolvedWeekSessionCounts(),
            (string) ($preview['groupingMode'] ?? SessionGroupingMode::defaultMode()),
            SessionGroupingMode::normalizeGroupSize(
                (int) ($preview['groupSize'] ?? SessionGroupingMode::defaultGroupSize()),
                (string) ($preview['groupingMode'] ?? SessionGroupingMode::defaultMode()),
            ),
        );
        $groupIndex = $strategyMap['groupIndexByWeekSession'][$weekIndex][$sessionIndex] ?? null;

        if ($groupIndex === null) {
            return [['week' => $weekIndex, 'session' => $sessionIndex]];
        }

        return collect($strategyMap['orderedSessions'])
            ->filter(fn (array $session): bool => (int) ($session['group'] ?? -1) === $groupIndex)
            ->map(fn (array $session): array => [
                'week' => (int) $session['week'],
                'session' => (int) $session['session'],
            ])
            ->values()
            ->all();
    }

    /** @param list<array{week:int, session:int}> $targets */
    protected function earliestOpenRebuildDateForTargets(array $targets): ?string
    {
        $dates = [];

        foreach ($targets as $target) {
            $week = (int) $target['week'];
            $session = (int) $target['session'];

            if ($this->isSessionLocked($week, $session)) {
                continue;
            }

            $date = $this->resolvedScheduledSessionDate($week, $session);

            if ($date !== null) {
                $dates[] = $date;
            }
        }

        sort($dates);

        return $dates[0] ?? null;
    }

    protected function isSessionLocked(int $weekIndex, int $sessionIndex): bool
    {
        return (bool) ($this->lockedSessionsByWeek[$weekIndex][$sessionIndex] ?? false);
    }

    protected function isSessionResetLocked(int $weekIndex, int $sessionIndex): bool
    {
        return $this->isSessionLocked($weekIndex, $sessionIndex);
    }

    protected function shouldRestrictPlannedEditForSession(int $weekIndex, int $sessionIndex): bool
    {
        return $this->isSessionLocked($weekIndex, $sessionIndex);
    }

    public function resetOverrides(): void
    {
        $overrides = $this->getCurrentOverrides();
        $overrides->gridOverrides = OverrideManager::reset();

        if ($this->userId !== null) {
            $overrides->inheritPlanGridOverrides = false;
        }

        $this->saveOverrides($overrides, snapshotLockedWeeks: false);
        $this->bumpGridRenderVersion();
        unset($this->configFingerprint, $this->previewGrid, $this->displayGrid, $this->planGridTable, $this->resolvedExerciseOverrides);
    }

    #[On('plan-overrides-reset')]
    public function onPlanOverridesReset(): void
    {
        $this->bumpGridRenderVersion();
        unset($this->configFingerprint, $this->previewGrid, $this->displayGrid, $this->planGridTable, $this->resolvedExerciseOverrides);
    }

    public function openSettingsForm(?string $focusField = null): void
    {
        if ($this->missingAthleteMeasurement) {
            return;
        }

        $effectiveConfig = $this->getEffectiveConfig();

        $this->dispatch('open-plan-exercise-settings', data: [
            'config' => $effectiveConfig,
            'programExerciseId' => $this->programExerciseId,
            'exerciseId' => $this->exerciseId,
            'userId' => $this->userId,
            'exerciseName' => $this->exerciseName,
            'photoUrls' => $this->baseExercisePhotoUrls(),
            'videoUrl' => $this->resolvedExerciseOverrides->effectiveVideoUrl ?? $this->baseExerciseVideoUrl(),
            'instructions' => $this->resolvedExerciseOverrides->effectiveInstructions ?? $this->baseExerciseInstructions(),
            'focusField' => $focusField,
        ]);

        $this->skipRender();
    }

    public function openGroupingForm(): void
    {
        $this->openSettingsForm('session_grouping');
    }

    protected function getParentConfig(): array
    {
        $base = $this->getExerciseConfig();

        if ($this->userId !== null) {
            return EffectiveExerciseConfig::resolve($base, $this->resolvedExerciseOverrides->defaultOverrides);
        }

        return $base->toArray();
    }

    protected function effectiveSessionGrouping(): SessionGroupingConfig
    {
        $preview = $this->getEffectiveConfig()['preview'] ?? [];

        return SessionGroupingConfig::from([
            'mode' => $preview['groupingMode'] ?? null,
            'groupSize' => $preview['groupSize'] ?? null,
            'copyValuesAutomatically' => $preview['copyValuesAutomatically'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $data */
    #[On('plan-exercise-settings.saved')]
    public function onSettingsSaved(array $data): void
    {
        if (($data['programExerciseId'] ?? null) !== $this->programExerciseId) {
            return;
        }

        if (($data['exerciseId'] ?? null) !== $this->exerciseId) {
            return;
        }

        if (($data['userId'] ?? null) !== $this->userId) {
            return;
        }

        if ($this->missingAthleteMeasurement) {
            return;
        }

        $settingsConfig = $data['config'] ?? [];
        $videoUrl = $this->normalizeContentText($data['videoUrl'] ?? null);
        $instructions = $this->normalizeInstructions($data['instructions'] ?? null);
        $parentConfig = $this->getParentConfig();
        $previousOverrides = ExerciseOverrides::from($this->getCurrentOverrides()->toArray());
        $overrides = ExerciseOverrides::from($previousOverrides->toArray());

        $parentVideoUrl = $this->parentVideoUrl();
        $overrides->videoUrl = $this->contentTextMatchesParent($videoUrl, $parentVideoUrl)
            ? null
            : $videoUrl;

        $parentInstructions = $this->parentInstructions();
        $overrides->instructions = $this->contentTextMatchesParent($instructions, $parentInstructions)
            ? null
            : $instructions;

        $overrides->settings = ($settingsConfig['settings'] ?? null) == ($parentConfig['settings'] ?? null)
            ? null
            : ($settingsConfig['settings'] ?? null);

        $formSets = $settingsConfig['sets'] ?? null;
        $parentSets = $parentConfig['sets'] ?? null;
        $comparableFormSets = ApplyPerScope::normalizeConfigForComparison($formSets);
        $comparableParentSets = ApplyPerScope::normalizeConfigForComparison($parentSets);

        if (is_array($formSets) && is_array($parentSets)) {
            $formSets = array_merge($parentSets, $formSets);
        }
        if (is_array($comparableFormSets) && is_array($comparableParentSets)) {
            $comparableFormSets = array_merge($comparableParentSets, $comparableFormSets);
        }

        $overrides->sets = $comparableFormSets == $comparableParentSets
            ? null
            : SetsSetting::from($formSets);

        $settingKeys = ['reps', 'weight', 'tempo', 'rest', 'distance', 'duration', 'heartRate', 'heartRateZone', 'pace', 'watts'];

        foreach ($settingKeys as $key) {
            $formValue = $settingsConfig[$key] ?? null;
            $parentValue = $parentConfig[$key] ?? null;
            $comparableFormValue = ApplyPerScope::normalizeConfigForComparison($formValue);
            $comparableParentValue = ApplyPerScope::normalizeConfigForComparison($parentValue);

            if (is_array($formValue) && is_array($parentValue)) {
                $formValue = array_merge($parentValue, $formValue);
            }

            if (is_array($comparableFormValue) && is_array($comparableParentValue)) {
                $comparableFormValue = array_merge($comparableParentValue, $comparableFormValue);
            }

            if ($comparableFormValue == $comparableParentValue) {
                $overrides->{$key} = null;
            } else {
                $enum = ExerciseSetting::tryFrom($key);
                if ($enum && $settingClass = $enum->settingClass()) {
                    $overrides->{$key} = isset($formValue) ? $settingClass::from($formValue) : null;
                }
            }
        }

        if ($this->userId === null && isset($settingsConfig['overrides'])) {
            $overrides->gridOverrides = $settingsConfig['overrides'];
        }

        $formGrouping = $this->sessionGroupingFromPreview($settingsConfig['preview'] ?? []);
        $parentGrouping = $this->sessionGroupingFromPreview($this->withResolvedPreviewGrouping($parentConfig)['preview'] ?? []);

        $overrides->sessionGrouping = $formGrouping->toArray() == $parentGrouping->toArray()
            ? null
            : $formGrouping;

        $gridAffectingChange = $this->gridAffectingOverrideSignature($previousOverrides) !== $this->gridAffectingOverrideSignature($overrides);

        $this->saveOverrides($overrides, notifyParent: $gridAffectingChange, snapshotLockedWeeks: false);

        if ($gridAffectingChange) {
            $this->bumpGridRenderVersion();
            unset($this->configFingerprint, $this->previewGrid, $this->displayGrid, $this->settingBadges, $this->resolvedExerciseOverrides, $this->copyBuckets, $this->copyMenuOptions, $this->resetMenuOptions, $this->groupingBadge);
        } else {
            unset($this->resolvedExerciseOverrides);
            $this->dispatch('exercise-content-overrides-changed');
            $this->skipRender();
        }
    }

    private function gridAffectingOverrideSignature(ExerciseOverrides $overrides): array
    {
        $data = $overrides->toArray();

        unset($data['videoUrl'], $data['instructions']);

        return $data;
    }

    private function parentVideoUrl(): ?string
    {
        if ($this->userId !== null) {
            return $this->resolvedExerciseOverrides->defaultOverrides->videoUrl ?? $this->baseExerciseVideoUrl();
        }

        return $this->baseExerciseVideoUrl();
    }

    private function parentInstructions(): ?string
    {
        if ($this->userId !== null) {
            return $this->resolvedExerciseOverrides->defaultOverrides->instructions ?? $this->baseExerciseInstructions();
        }

        return $this->baseExerciseInstructions();
    }

    private function baseExerciseVideoUrl(): ?string
    {
        $videoUrl = Exercise::query()
            ->whereKey($this->exerciseId)
            ->value('video_url');

        return $this->normalizeContentText($videoUrl);
    }

    private function baseExerciseInstructions(): ?string
    {
        $instructions = Exercise::query()
            ->whereKey($this->exerciseId)
            ->value('instructions');

        return is_string($instructions) && trim($instructions) !== ''
            ? trim($instructions)
            : null;
    }

    private function baseExercisePhotoUrls(): array
    {
        if (! Schema::hasTable('media')) {
            return [];
        }

        $exercise = Exercise::query()->find($this->exerciseId);

        if (! $exercise) {
            return [];
        }

        return $exercise->getMedia('photos')->map(fn ($media) => $media->getUrl())->values()->all();
    }

    private function contentTextMatchesParent(?string $value, ?string $parentValue): bool
    {
        return $value === $parentValue
            || ($value === '' && $parentValue === null);
    }

    private function normalizeInstructions(mixed $instructions): ?string
    {
        return $this->normalizeContentText($instructions);
    }

    private function normalizeContentText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return trim($value);
    }

    private function sessionGroupingFromPreview(array $preview): SessionGroupingConfig
    {
        return SessionGroupingConfig::from([
            'mode' => $preview['groupingMode'] ?? null,
            'groupSize' => $preview['groupSize'] ?? null,
            'copyValuesAutomatically' => $preview['copyValuesAutomatically'] ?? null,
        ]);
    }

    protected function saveOverrides(ExerciseOverrides $overrides, bool $notifyParent = true, ?string $openRebuildFromDate = null, bool $snapshotLockedWeeks = true): void
    {
        $span = PlanGridProfiler::start('PlanExerciseGrid.saveOverrides', $this->profileContext([
            'notify_parent' => $notifyParent,
            'grid_override_cells' => count($overrides->gridOverrides['cells'] ?? []),
            'grid_override_sessions' => count($overrides->gridOverrides['sessions'] ?? []),
            'historical_cells' => count($overrides->historicalGridOverrides['cells'] ?? []),
            'open_rebuild_from_date' => $openRebuildFromDate,
        ]));

        try {
            if ($snapshotLockedWeeks) {
                PlanGridProfiler::measure('PlanExerciseGrid.saveOverrides.snapshotLockedWeeks', $this->profileContext(), function () use ($overrides): void {
                    $this->snapshotLockedWeeks($overrides, $this->previewGrid);
                });
            }

            $overrides->historicalGridOverrides = $this->normalizeHistoricalOverridesForGroupedSessions(
                $overrides->historicalGridOverrides,
            );
            $overrides->gridOverrides = GridOverrideNormalizer::pruneToSessionCounts(
                $overrides->gridOverrides,
                $this->resolvedWeekSessionCounts(),
            );

            if ($this->usesFixedSessionGroupCoordinates()) {
                $overrides->gridOverrides = $this->remapGridOverrideCoordinates(
                    $overrides->gridOverrides,
                    $this->fixedSessionCoordinateMaps()['visibleToCanonical'],
                );
                $overrides->ignoredPlanGridOverrideSessions = $this->remapIgnoredSessionCoordinates(
                    $overrides->ignoredPlanGridOverrideSessions,
                    $this->fixedSessionCoordinateMaps()['visibleToCanonical'],
                );
            }

            $exerciseProgram = PlanGridProfiler::measure('PlanExerciseGrid.saveOverrides.loadExerciseProgram', $this->profileContext(), function (): ExerciseProgram {
                return ExerciseProgram::query()->findOrFail($this->planId);
            });

            $config = $exerciseProgram->config;
            $previousOverrides = PlanGridProfiler::measure('PlanExerciseGrid.saveOverrides.previousOverrides', $this->profileContext(), function () use ($config): ExerciseOverrides {
                return $config->exerciseOverrides($this->programExerciseId, $this->userId);
            });
            $previousGridOverrides = $previousOverrides->gridOverrides;
            $openSessionsAffected = $this->openSessionAffectingOverridesChanged($previousOverrides, $overrides);
            $config->setExerciseOverrides($this->programExerciseId, $overrides, $this->userId);

            $exerciseProgram->config = $config;
            $shouldScopeRebuildToAthlete = $this->userId !== null;

            PlanGridProfiler::measure('PlanExerciseGrid.saveOverrides.persistExerciseProgram', $this->profileContext(), function () use ($exerciseProgram): void {
                $exerciseProgram->saveQuietly();
            });

            if ($openSessionsAffected) {
                PlanGridProfiler::measure('PlanExerciseGrid.saveOverrides.dispatchOpenRebuild', $this->profileContext([
                    'scope_user_id' => $shouldScopeRebuildToAthlete ? $this->userId : null,
                    'from_date' => $openRebuildFromDate,
                ]), function () use ($exerciseProgram, $shouldScopeRebuildToAthlete, $openRebuildFromDate): void {
                    $this->dispatchOpenRebuild($exerciseProgram->id, $shouldScopeRebuildToAthlete ? $this->userId : null, $openRebuildFromDate);
                });
            }

            PlanGridProfiler::measure('PlanExerciseGrid.saveOverrides.recordGridOverrideChanges', $this->profileContext(), function () use ($exerciseProgram, $previousGridOverrides, $overrides): void {
                app(TrainingPlanRevisionService::class)->recordGridOverrideChanges(
                    owner: $exerciseProgram,
                    programExerciseId: $this->programExerciseId,
                    userId: $this->userId,
                    before: $previousGridOverrides,
                    after: $overrides->gridOverrides,
                    fieldConfigMap: $this->planRevisionFieldConfigMap(),
                    action: $this->resolvePlanRevisionAction($previousGridOverrides, $overrides->gridOverrides),
                );
            });

            PlanGridProfiler::measure('PlanExerciseGrid.saveOverrides.recordOverrideSettingChanges', $this->profileContext(), function () use ($exerciseProgram, $previousOverrides, $overrides): void {
                app(TrainingPlanRevisionService::class)->recordOverrideSettingChanges(
                    owner: $exerciseProgram,
                    programExerciseId: $this->programExerciseId,
                    userId: $this->userId,
                    before: $previousOverrides,
                    after: $overrides,
                    action: $this->resolvePlanSettingsRevisionAction($previousOverrides, $overrides),
                );
            });

            PlanGridProfiler::measure('PlanExerciseGrid.saveOverrides.syncLockedHistoricalSessionSnapshots', $this->profileContext(), function () use ($overrides): void {
                $this->syncLockedHistoricalSessionSnapshots($overrides);
            });

            PlanGridProfiler::measure('PlanExerciseGrid.saveOverrides.refreshComponentConfig', $this->profileContext(), function () use ($config): void {
                $this->planConfigArray = $this->slicePlanConfigArray($config->toArray());
                unset($this->planConfigArray['overrideValues']);
                unset($this->planConfig, $this->resolvedExerciseOverrides);
            });

            if ($notifyParent) {
                PlanGridProfiler::mark('PlanExerciseGrid.saveOverrides.dispatchParentChanged', $this->profileContext());
                $this->dispatch('exercise-overrides-changed');
            }
        } finally {
            PlanGridProfiler::end($span, [
                'open_sessions_affected' => $openSessionsAffected ?? null,
            ]);
        }
    }

    protected function openSessionAffectingOverridesChanged(ExerciseOverrides $before, ExerciseOverrides $after): bool
    {
        return $this->openSessionAffectingOverrideSignature($before) !== $this->openSessionAffectingOverrideSignature($after);
    }

    protected function openSessionAffectingOverrideSignature(ExerciseOverrides $overrides): array
    {
        $data = $overrides->toArray();

        unset($data['historicalGridOverrides'], $data['baselineGridOverrides']);

        return $data;
    }

    private function slicePlanConfigArray(array $config): array
    {
        if ($config === []) {
            return [];
        }

        $programExerciseId = (int) $this->programExerciseId;
        $userId = $this->userId !== null ? (int) $this->userId : null;

        $config['exercises'] = array_filter(
            $config['exercises'] ?? [],
            fn (int|string $key): bool => (int) $key === $programExerciseId,
            ARRAY_FILTER_USE_KEY,
        );

        $userExercises = [];

        foreach (($config['userExercises'] ?? []) as $candidateUserId => $overridesByExercise) {
            if ($userId === null || (int) $candidateUserId !== $userId || ! is_array($overridesByExercise)) {
                continue;
            }

            $filtered = array_filter(
                $overridesByExercise,
                fn (int|string $key): bool => (int) $key === $programExerciseId,
                ARRAY_FILTER_USE_KEY,
            );

            if ($filtered !== []) {
                $userExercises[$candidateUserId] = $filtered;
            }
        }

        $config['userExercises'] = $userExercises;
        $config['overrideValues'] = array_values(array_filter(
            $config['overrideValues'] ?? [],
            function (array $row) use ($programExerciseId, $userId): bool {
                if ((int) ($row['programExerciseId'] ?? 0) !== $programExerciseId) {
                    return false;
                }

                $rowUserId = array_key_exists('userId', $row) && $row['userId'] !== null
                    ? (int) $row['userId']
                    : null;

                return $rowUserId === null || ($userId !== null && $rowUserId === $userId);
            },
        ));

        return $config;
    }

    protected function dispatchOpenRebuild(int $exerciseProgramId, ?int $userId, ?string $fromDate = null): void
    {
        $dispatcher = app(TrainingSessionRebuildDispatcher::class);

        if ($this->scheduledTrainingProgramId !== null) {
            $dispatcher->dispatchOpenSlotsForTrainingProgramChange($this->scheduledTrainingProgramId, $userId, $fromDate);

            return;
        }

        $dispatcher->dispatchOpenSlotsForExerciseProgramChange($exerciseProgramId, $userId, $fromDate);
    }

    /** @return array<string, array<string, mixed>> */
    protected function planRevisionFieldConfigMap(): array
    {
        $map = [];
        $sets = $this->getExerciseConfig()->sets ?? null;
        $setsConfig = is_object($sets) && method_exists($sets, 'toArray')
            ? $sets->toArray()
            : [];

        foreach (ExerciseSetting::cases() as $setting) {
            $field = $setting->value;
            $config = $this->getExerciseConfig()->{$field} ?? null;

            $map[$field] = is_object($config) && method_exists($config, 'toArray')
                ? $config->toArray()
                : [];
            $map[$field]['_sets'] = $setsConfig;
        }

        return $map;
    }

    /** @param array{sessions?: array<int, array<string, mixed>>, cells?: array<int, array<string, mixed>>} $before
     * @param  array{sessions?: array<int, array<string, mixed>>, cells?: array<int, array<string, mixed>>}  $after
     */
    protected function resolvePlanRevisionAction(array $before, array $after): string
    {
        $beforeHasRows = ! empty($before['sessions'] ?? []) || ! empty($before['cells'] ?? []);
        $afterHasRows = ! empty($after['sessions'] ?? []) || ! empty($after['cells'] ?? []);

        return match (true) {
            ! $beforeHasRows && $afterHasRows => 'create_grid_overrides',
            $beforeHasRows && ! $afterHasRows => 'reset_grid_overrides',
            default => 'update_grid_overrides',
        };
    }

    protected function resolvePlanSettingsRevisionAction(ExerciseOverrides $before, ExerciseOverrides $after): string
    {
        $beforeHasOverrides = $this->hasTrackedSettingOverrides($before);
        $afterHasOverrides = $this->hasTrackedSettingOverrides($after);

        return match (true) {
            ! $beforeHasOverrides && $afterHasOverrides => 'create_setting_overrides',
            $beforeHasOverrides && ! $afterHasOverrides => 'reset_setting_overrides',
            default => 'update_setting_overrides',
        };
    }

    protected function hasTrackedSettingOverrides(ExerciseOverrides $overrides): bool
    {
        foreach ([
            'settings',
            'videoUrl',
            'instructions',
            'startsAtDate',
            'sets',
            'reps',
            'weight',
            'tempo',
            'rest',
            'distance',
            'duration',
            'heartRate',
            'heartRateZone',
            'pace',
            'watts',
            'sessionGrouping',
            'disabled',
        ] as $field) {
            if (($overrides->{$field} ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    protected function snapshotLockedWeeks(ExerciseOverrides $overrides, PreviewGrid $grid): void
    {
        $historicalGridOverrides = $overrides->historicalGridOverrides;

        foreach (range(0, $grid->weekCount - 1) as $week) {
            $weekLockedSessions = $this->lockedSessionsByWeek[$week] ?? [];

            if (! in_array(true, $weekLockedSessions, true)) {
                continue;
            }

            foreach ($grid->rows as $row) {
                foreach ($weekLockedSessions as $session => $isLocked) {
                    if (! $isLocked) {
                        continue;
                    }

                    foreach ($row->cells[$week] ?? [] as $set => $_value) {
                        $value = $row->getCellValue($week, (int) $set, $session);

                        if ($value === null || $value === '' || $value === '-' || $value === '—') {
                            continue;
                        }

                        $historicalGridOverrides = $this->putCellOverride($historicalGridOverrides, $week, $session, (int) $set, $row->field, $value);
                    }
                }
            }

            foreach ($grid->weekColumns as $column) {
                foreach ($weekLockedSessions as $session => $isLocked) {
                    if (! $isLocked) {
                        continue;
                    }

                    $value = $column->getCellValue($week, 0, (int) $session);

                    if ($value === null || $value === '' || $value === '-' || $value === '—') {
                        continue;
                    }

                    $historicalGridOverrides = $this->putSessionOverride($historicalGridOverrides, $week, (int) $session, $column->field, $value);
                }
            }
        }

        $overrides->historicalGridOverrides = $historicalGridOverrides;
        $overrides->gridOverrides = $this->stripLockedHistoryFromCurrentOverrides($overrides->gridOverrides);
    }

    /** @param array{cells?: array<int, array<string, mixed>>, sessions?: array<int, array<string, mixed>>} $historicalGridOverrides */
    protected function normalizeHistoricalOverridesForGroupedSessions(array $historicalGridOverrides): array
    {
        $preview = $this->getEffectiveConfig()['preview'] ?? [];
        $groupingMode = SessionGroupingMode::normalizeMode((string) ($preview['groupingMode'] ?? SessionGroupingMode::defaultMode()));

        if ($groupingMode !== SessionGroupingMode::Groups->value) {
            return $historicalGridOverrides;
        }

        $strategyMap = SessionGroupBuilder::buildStrategyMap(
            weekCount: $this->previewGrid->weekCount,
            sessionCounts: $this->previewGrid->weekSessionCounts,
            groupingMode: $groupingMode,
            groupSize: SessionGroupingMode::normalizeGroupSize(
                (int) ($preview['groupSize'] ?? SessionGroupingMode::defaultGroupSize($groupingMode)),
                $groupingMode,
            ),
        );

        $sessionsByGroup = collect($strategyMap['orderedSessions'] ?? [])
            ->filter(fn (array $session): bool => (bool) ($this->lockedSessionsByWeek[(int) $session['week']][(int) $session['session']] ?? false))
            ->groupBy(fn (array $session): int => (int) $session['group']);

        foreach ($sessionsByGroup as $groupSessions) {
            $sessions = $groupSessions->values()->all();

            if (count($sessions) <= 1) {
                continue;
            }

            foreach ($this->groupedHistoricalSessionValues($historicalGridOverrides, $sessions) as $field => $value) {
                foreach ($sessions as $session) {
                    $historicalGridOverrides = $this->putSessionOverride(
                        $historicalGridOverrides,
                        (int) $session['week'],
                        (int) $session['session'],
                        (string) $field,
                        $value,
                    );
                }
            }

            foreach ($this->groupedHistoricalCellValues($historicalGridOverrides, $sessions) as $set => $fields) {
                foreach ($fields as $field => $value) {
                    foreach ($sessions as $session) {
                        $historicalGridOverrides = $this->putCellOverride(
                            $historicalGridOverrides,
                            (int) $session['week'],
                            (int) $session['session'],
                            (int) $set,
                            (string) $field,
                            $value,
                        );
                    }
                }
            }
        }

        return $historicalGridOverrides;
    }

    /** @return list<array{week:int, session:int, sessionNumber:int, group:int}> */
    protected function lockedHistoricalTargetsForSession(int $weekIndex, int $sessionIndex): array
    {
        if (! $this->isSessionLocked($weekIndex, $sessionIndex)) {
            return [];
        }

        $preview = $this->getEffectiveConfig()['preview'] ?? [];
        $groupingMode = SessionGroupingMode::normalizeMode((string) ($preview['groupingMode'] ?? SessionGroupingMode::defaultMode()));

        if ($groupingMode !== SessionGroupingMode::Groups->value) {
            return [[
                'week' => $weekIndex,
                'session' => $sessionIndex,
                'sessionNumber' => $sessionIndex + 1,
                'group' => $sessionIndex + 1,
            ]];
        }

        $strategyMap = SessionGroupBuilder::buildStrategyMap(
            weekCount: $this->previewGrid->weekCount,
            sessionCounts: $this->previewGrid->weekSessionCounts,
            groupingMode: $groupingMode,
            groupSize: SessionGroupingMode::normalizeGroupSize(
                (int) ($preview['groupSize'] ?? SessionGroupingMode::defaultGroupSize($groupingMode)),
                $groupingMode,
            ),
        );

        $editedSession = collect($strategyMap['orderedSessions'] ?? [])
            ->first(fn (array $session): bool => (int) $session['week'] === $weekIndex
                && (int) $session['session'] === $sessionIndex);

        if (! is_array($editedSession)) {
            return [[
                'week' => $weekIndex,
                'session' => $sessionIndex,
                'sessionNumber' => $sessionIndex + 1,
                'group' => $sessionIndex + 1,
            ]];
        }

        $group = (int) $editedSession['group'];

        return collect($strategyMap['orderedSessions'] ?? [])
            ->filter(fn (array $session): bool => (int) $session['group'] === $group)
            ->filter(fn (array $session): bool => $this->isSessionLocked((int) $session['week'], (int) $session['session']))
            ->map(fn (array $session): array => [
                'week' => (int) $session['week'],
                'session' => (int) $session['session'],
                'sessionNumber' => (int) $session['sessionNumber'],
                'group' => (int) $session['group'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{cells?: array<int, array<string, mixed>>, sessions?: array<int, array<string, mixed>>}  $historicalGridOverrides
     * @param  list<array{week:int, session:int, sessionNumber:int, group:int}>  $sessions
     * @return array<string, mixed>
     */
    protected function groupedHistoricalSessionValues(array $historicalGridOverrides, array $sessions): array
    {
        $values = [];

        foreach ($sessions as $session) {
            $sessionOverride = collect($historicalGridOverrides['sessions'] ?? [])
                ->first(fn (array $override): bool => (int) ($override['week'] ?? -1) === (int) $session['week']
                    && (int) ($override['session'] ?? -1) === (int) $session['session']);

            foreach (($sessionOverride['data'] ?? []) as $field => $value) {
                $values[$field] ??= $value;
            }
        }

        return $values;
    }

    /**
     * @param  array{cells?: array<int, array<string, mixed>>, sessions?: array<int, array<string, mixed>>}  $historicalGridOverrides
     * @param  list<array{week:int, session:int, sessionNumber:int, group:int}>  $sessions
     * @return array<int, array<string, mixed>>
     */
    protected function groupedHistoricalCellValues(array $historicalGridOverrides, array $sessions): array
    {
        $values = [];

        foreach ($sessions as $session) {
            foreach ($historicalGridOverrides['cells'] ?? [] as $cellOverride) {
                if (
                    (int) ($cellOverride['week'] ?? -1) !== (int) $session['week']
                    || (int) ($cellOverride['session'] ?? -1) !== (int) $session['session']
                ) {
                    continue;
                }

                $set = (int) ($cellOverride['set'] ?? -1);

                if ($set < 0) {
                    continue;
                }

                foreach (($cellOverride['data'] ?? []) as $field => $value) {
                    $values[$set][$field] ??= $value;
                }
            }
        }

        return $values;
    }

    protected function sessionCountForWeek(int $weekIndex): int
    {
        return WeekSessionCountResolver::resolveForWeek(
            weekIndex: $weekIndex,
            fallbackSessionsPerWeek: $this->effectivePreviewSessionsPerWeek($this->getEffectiveConfig()),
            weekSessions: $this->weekSessions,
            weekSessionDates: $this->weekSessionDates,
            lockedSessionsByWeek: $this->lockedSessionsByWeek,
        );
    }

    protected function effectivePreviewSessionsPerWeek(array $config): int
    {
        return SessionGroupingMode::resolvePreviewSessionCount($config['preview'] ?? [], $this->sessionsPerWeek);
    }

    protected function isEntireWeekLocked(int $weekIndex): bool
    {
        $sessionCount = $this->sessionCountForWeek($weekIndex);

        if ($sessionCount <= 0) {
            return false;
        }

        for ($session = 0; $session < $sessionCount; $session++) {
            if (! ($this->lockedSessionsByWeek[$weekIndex][$session] ?? false)) {
                return false;
            }
        }

        return true;
    }

    /** @return array{cells: array, weeks: array} */
    protected function stripLockedHistoryFromCurrentOverrides(array $gridOverrides): array
    {
        $gridOverrides['cells'] = collect($gridOverrides['cells'] ?? [])
            ->reject(function (array $cell): bool {
                $week = (int) ($cell['week'] ?? -1);
                $session = array_key_exists('session', $cell) ? (int) $cell['session'] : null;

                return $session !== null && ($this->lockedSessionsByWeek[$week][$session] ?? false);
            })
            ->values()
            ->all();

        $gridOverrides['sessions'] = collect($gridOverrides['sessions'] ?? [])
            ->reject(fn (array $session): bool => ($this->lockedSessionsByWeek[(int) ($session['week'] ?? -1)][(int) ($session['session'] ?? -1)] ?? false))
            ->values()
            ->all();

        return $gridOverrides;
    }

    protected function resolvedWeekSessionCounts(): array
    {
        $counts = [];

        foreach (range(0, $this->weeks - 1) as $week) {
            $counts[$week] = $this->sessionCountForWeek($week);
        }

        return $counts;
    }

    protected function usesFixedSessionGroupCoordinates(): bool
    {
        if ($this->scheduledTrainingProgramId === null) {
            return false;
        }

        return SessionGroupingMode::normalizeMode(
            (string) ($this->getEffectiveConfig()['preview']['groupingMode'] ?? SessionGroupingMode::defaultMode()),
        ) === SessionGroupingMode::Groups->value;
    }

    /**
     * @return array{
     *     canonicalToVisible: array<string, array{week:int, session:int}>,
     *     visibleToCanonical: array<string, array{week:int, session:int}>
     * }
     */
    protected function fixedSessionCoordinateMaps(): array
    {
        $preview = $this->getEffectiveConfig()['preview'] ?? [];
        $groupSize = SessionGroupingMode::normalizeGroupSize(
            isset($preview['groupSize']) ? (int) $preview['groupSize'] : null,
            SessionGroupingMode::Groups->value,
        );
        $strategyMap = SessionGroupBuilder::buildStrategyMap(
            weekCount: $this->weeks,
            sessionCounts: $this->resolvedWeekSessionCounts(),
            groupingMode: SessionGroupingMode::Groups->value,
            groupSize: $groupSize,
        );
        $canonicalToVisible = [];
        $visibleToCanonical = [];

        foreach ($strategyMap['orderedSessions'] as $visible) {
            $canonical = [
                'week' => (int) $visible['group'],
                'session' => ((int) $visible['sessionNumber'] - 1) % $groupSize,
            ];
            $visibleCoordinate = [
                'week' => (int) $visible['week'],
                'session' => (int) $visible['session'],
            ];
            $canonicalToVisible[$canonical['week'].':'.$canonical['session']] = $visibleCoordinate;
            $visibleToCanonical[$visibleCoordinate['week'].':'.$visibleCoordinate['session']] = $canonical;
        }

        return compact('canonicalToVisible', 'visibleToCanonical');
    }

    /** @param array<string, array{week:int, session:int}> $coordinateMap */
    protected function remapGridOverrideCoordinates(array $gridOverrides, array $coordinateMap): array
    {
        $remapped = ['sessions' => [], 'cells' => []];

        foreach (['sessions', 'cells'] as $target) {
            foreach ($gridOverrides[$target] ?? [] as $entry) {
                $coordinate = $coordinateMap[((int) ($entry['week'] ?? 0)).':'.((int) ($entry['session'] ?? 0))] ?? null;

                if ($coordinate !== null) {
                    $entry['week'] = $coordinate['week'];
                    $entry['session'] = $coordinate['session'];
                }

                $layer = ['sessions' => [], 'cells' => []];
                $layer[$target][] = $entry;
                $remapped = EffectiveExerciseConfig::mergeGridOverrides($remapped, $layer);
            }
        }

        return $remapped;
    }

    /**
     * @param  list<string>  $ignoredSessions
     * @param  array<string, array{week:int, session:int}>  $coordinateMap
     * @return list<string>
     */
    protected function remapIgnoredSessionCoordinates(array $ignoredSessions, array $coordinateMap): array
    {
        return collect($ignoredSessions)
            ->map(function (string $key) use ($coordinateMap): string {
                $coordinate = $coordinateMap[$key] ?? null;

                return $coordinate === null ? $key : $coordinate['week'].':'.$coordinate['session'];
            })
            ->unique()
            ->values()
            ->all();
    }

    protected function weekHasSessionDivergence(PreviewGrid $grid, int $week): bool
    {
        $sessionCount = $grid->weekSessionCounts[$week] ?? $this->sessionCountForWeek($week);

        if ($sessionCount <= 1) {
            return false;
        }

        foreach ($grid->rows as $row) {
            if ($row->lastSessionOnly) {
                continue;
            }

            foreach (array_keys($row->cells[$week] ?? []) as $set) {
                $baselineValue = $row->getCellValue($week, (int) $set, 0);
                $baselineOverride = $row->isCellOverriddenAt($week, (int) $set, 0);

                for ($session = 1; $session < $sessionCount; $session++) {
                    if ($row->getCellValue($week, (int) $set, $session) !== $baselineValue) {
                        return true;
                    }

                    if ($row->isCellOverriddenAt($week, (int) $set, $session) !== $baselineOverride) {
                        return true;
                    }
                }
            }
        }

        foreach ($grid->weekColumns as $column) {
            $baselineValue = $column->getCellValue($week, 0, 0);
            $baselineOverride = $column->isCellOverriddenAt($week, 0, 0);

            for ($session = 1; $session < $sessionCount; $session++) {
                if ($column->getCellValue($week, 0, $session) !== $baselineValue) {
                    return true;
                }

                if ($column->isCellOverriddenAt($week, 0, $session) !== $baselineOverride) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function groupHasSessionDivergence(PreviewGrid $grid, $group): bool
    {
        $sessions = $group->sessions ?? [];

        if (count($sessions) <= 1) {
            return false;
        }

        $baseline = $sessions[0] ?? null;
        if ($baseline === null) {
            return false;
        }

        foreach ($grid->rows as $row) {
            if ($row->lastSessionOnly) {
                continue;
            }

            foreach (range(0, $grid->setCount - 1) as $set) {
                $baselineValue = $row->getCellValue($baseline->weekIndex, $set, $baseline->sessionIndex);
                $baselineOverride = $row->isCellOverriddenAt($baseline->weekIndex, $set, $baseline->sessionIndex);

                foreach (array_slice($sessions, 1) as $session) {
                    if ($row->getCellValue($session->weekIndex, $set, $session->sessionIndex) !== $baselineValue) {
                        return true;
                    }

                    if ($row->isCellOverriddenAt($session->weekIndex, $set, $session->sessionIndex) !== $baselineOverride) {
                        return true;
                    }
                }
            }
        }

        foreach ($grid->weekColumns as $column) {
            $baselineValue = $column->getCellValue($baseline->weekIndex, 0, $baseline->sessionIndex);
            $baselineOverride = $column->isCellOverriddenAt($baseline->weekIndex, 0, $baseline->sessionIndex);

            foreach (array_slice($sessions, 1) as $session) {
                if ($column->getCellValue($session->weekIndex, 0, $session->sessionIndex) !== $baselineValue) {
                    return true;
                }

                if ($column->isCellOverriddenAt($session->weekIndex, 0, $session->sessionIndex) !== $baselineOverride) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array<int, mixed> $groups
     * @return int[]
     */
    protected function forcedExpandedGroupIndexes(PreviewGrid $grid, array $groups): array
    {
        return collect($groups)
            ->filter(function ($group) use ($grid): bool {
                $sessions = $group->sessions ?? [];

                if (count($sessions) <= 1) {
                    return false;
                }

                $lockStates = collect($sessions)
                    ->map(fn ($session): bool => (bool) ($session->locked ?? false))
                    ->unique();

                if ($lockStates->count() > 1) {
                    return true;
                }

                return $this->groupHasSessionDivergence($grid, $group);
            })
            ->map(fn ($group): int => (int) $group->index)
            ->values()
            ->all();
    }

    protected function putCellOverride(array $gridOverrides, int $week, int $session, int $set, string $field, mixed $value): array
    {
        foreach ($gridOverrides['cells'] ?? [] as $index => $override) {
            if (($override['week'] ?? null) === $week
                && ($override['session'] ?? null) === $session
                && ($override['set'] ?? null) === $set) {
                $gridOverrides['cells'][$index]['data'][$field] = $value;

                return $gridOverrides;
            }
        }

        $gridOverrides['cells'][] = [
            'week' => $week,
            'session' => $session,
            'set' => $set,
            'data' => [$field => $value],
        ];

        return $gridOverrides;
    }

    protected function putSessionOverride(array $gridOverrides, int $week, int $session, string $field, mixed $value): array
    {
        foreach ($gridOverrides['sessions'] ?? [] as $index => $override) {
            if (($override['week'] ?? null) === $week && ($override['session'] ?? null) === $session) {
                $gridOverrides['sessions'][$index]['data'][$field] = $value;

                return $gridOverrides;
            }
        }

        $gridOverrides['sessions'][] = [
            'week' => $week,
            'session' => $session,
            'data' => [$field => $value],
        ];

        return $gridOverrides;
    }

    protected function syncLockedHistoricalSessionSnapshots(ExerciseOverrides $overrides): void
    {
        if ($this->scheduledTrainingProgramId === null || $this->userId === null) {
            return;
        }

        $historicalOverrides = $overrides->historicalGridOverrides;

        foreach ($this->lockedHistoricalSessionPayloads($historicalOverrides) as $sessionPayload) {
            $slotExercise = $this->slotExerciseForLockedSessionPayload(
                $sessionPayload['week'],
                $sessionPayload['session'],
            );

            if (! $slotExercise instanceof TrainingProgramSlotExercise) {
                continue;
            }

            app(TrainingSessionPlannedValueService::class)->saveExercisePlannedValues(
                $slotExercise,
                $sessionPayload['values'],
                onlyProvided: true,
            );
        }
    }

    /**
     * @param  array{cells?: array<int, array<string, mixed>>, sessions?: array<int, array<string, mixed>>}  $historicalOverrides
     * @return list<array{week: int, session: int, values: array<int, array<string, mixed>>}>
     */
    protected function lockedHistoricalSessionPayloads(array $historicalOverrides): array
    {
        $payloads = [];

        foreach ($historicalOverrides['cells'] ?? [] as $cellOverride) {
            $week = (int) ($cellOverride['week'] ?? -1);
            $session = (int) ($cellOverride['session'] ?? -1);
            $setIndex = (int) ($cellOverride['set'] ?? -1);

            if (! ($this->lockedSessionsByWeek[$week][$session] ?? false) || $setIndex < 0) {
                continue;
            }

            $payloads[$week][$session]['week'] = $week;
            $payloads[$week][$session]['session'] = $session;
            $payloads[$week][$session]['values'][$setIndex] = array_merge(
                $payloads[$week][$session]['values'][$setIndex] ?? [],
                (array) ($cellOverride['data'] ?? []),
            );
        }

        foreach ($historicalOverrides['sessions'] ?? [] as $sessionOverride) {
            $week = (int) ($sessionOverride['week'] ?? -1);
            $session = (int) ($sessionOverride['session'] ?? -1);

            if (! ($this->lockedSessionsByWeek[$week][$session] ?? false)) {
                continue;
            }

            $payloads[$week][$session]['week'] = $week;
            $payloads[$week][$session]['session'] = $session;
            $sessionFields = (array) ($sessionOverride['data'] ?? []);

            if ($this->previewGrid->setCount <= 0) {
                continue;
            }

            foreach (range(0, $this->previewGrid->setCount - 1) as $setIndex) {
                $payloads[$week][$session]['values'][$setIndex] = array_merge(
                    $payloads[$week][$session]['values'][$setIndex] ?? [],
                    $sessionFields,
                );
            }
        }

        return collect($payloads)
            ->flatMap(fn (array $sessions): array => array_values($sessions))
            ->map(function (array $payload): array {
                $slotExercise = $this->slotExerciseForLockedSessionPayload($payload['week'], $payload['session']);

                if (! $slotExercise instanceof TrainingProgramSlotExercise) {
                    return $payload + ['values' => []];
                }

                $orderedSets = $slotExercise->sets
                    ->sortBy('set_number')
                    ->values();

                $values = [];

                foreach (($payload['values'] ?? []) as $setIndex => $setValues) {
                    $set = $orderedSets->get((int) $setIndex);

                    if (! $set instanceof TrainingProgramSlotSet) {
                        continue;
                    }

                    $values[$set->id] = $setValues;
                }

                return [
                    'week' => (int) $payload['week'],
                    'session' => (int) $payload['session'],
                    'values' => $values,
                ];
            })
            ->filter(fn (array $payload): bool => $payload['values'] !== [])
            ->values()
            ->all();
    }

    protected function slotExerciseForLockedSessionPayload(int $weekIndex, int $sessionIndex): ?TrainingProgramSlotExercise
    {
        return $this->slotExerciseForWeekSession($weekIndex, $sessionIndex);
    }

    protected function profileContext(array $extra = []): array
    {
        return array_merge([
            'component' => static::class,
            'plan_id' => $this->planId ?? null,
            'scheduled_training_program_id' => $this->scheduledTrainingProgramId ?? null,
            'program_exercise_id' => $this->programExerciseId ?? null,
            'exercise_id' => $this->exerciseId ?? null,
            'user_id' => $this->userId ?? null,
            'weeks' => $this->weeks ?? null,
            'sessions_per_week' => $this->sessionsPerWeek ?? null,
            'value_display_mode' => $this->valueDisplayMode ?? null,
            'grid_render_version' => $this->gridRenderVersion ?? null,
        ], $extra);
    }

    public function hydrate(): void
    {
        PlanGridProfiler::mark('PlanExerciseGrid.hydrate', $this->profileContext());
    }

    public function dehydrate(): void
    {
        PlanGridProfiler::mark('PlanExerciseGrid.dehydrate', $this->profileContext());
    }

    public function render()
    {
        return PlanGridProfiler::measure('PlanExerciseGrid.render', $this->profileContext(), function () {
            return view('livewire.training.view.plan-exercise-grid');
        });
    }

    protected function displayGridForCopy(): PreviewGrid
    {
        return $this->displayGrid;
    }

    protected function previewGridForCopy(): PreviewGrid
    {
        return $this->previewGrid;
    }

    protected function defaultsGridForCopy(): PreviewGrid
    {
        return $this->buildDefaultsGrid();
    }

    protected function expandedIndexesForCopy(): array
    {
        return $this->effectiveExpandedWeeks;
    }

    protected function currentGridOverridesForCopy(): array
    {
        return $this->getCurrentOverrides()->gridOverrides;
    }

    protected function usesSessionOnlyDisplayCopy(): bool
    {
        return SessionGroupingMode::normalizeMode(
            (string) ($this->getEffectiveConfig()['preview']['groupingMode'] ?? SessionGroupingMode::defaultMode())
        ) === SessionGroupingMode::None->value;
    }

    protected function resolvedScheduledSessionDate(int $weekIndex, int $sessionIndex): ?string
    {
        $date = $this->weekSessionDates[$weekIndex][$sessionIndex] ?? null;

        return is_string($date) && $date !== '' ? $date : null;
    }

    /** @return list<string> */
    protected function scheduledSessionDates(): array
    {
        return collect($this->weekSessionDates)
            ->flatMap(fn (array $datesForWeek): array => array_values($datesForWeek))
            ->filter(fn (mixed $date): bool => is_string($date) && $date !== '')
            ->unique()
            ->values()
            ->all();
    }

    protected function slotDateKey(TrainingProgramSlot $slot): string
    {
        return ($slot->scheduled_date ?? $slot->datetime)->format('Y-m-d');
    }

    protected function resolveScheduledSlotsByDate(): array
    {
        if (is_array($this->loadedScheduledSlotsByDate)) {
            return $this->loadedScheduledSlotsByDate;
        }

        if ($this->scheduledTrainingProgramId === null || $this->userId === null) {
            return $this->loadedScheduledSlotsByDate = [];
        }

        $dates = $this->scheduledSessionDates();

        if ($dates === []) {
            return $this->loadedScheduledSlotsByDate = [];
        }

        $start = Carbon::parse(min($dates))->startOfDay();
        $end = Carbon::parse(max($dates))->endOfDay();
        $dateLookup = array_flip($dates);
        $cacheKey = $this->sharedScheduledDataCacheKey($dates);
        $cache = self::scheduledDataCache();
        $slotsCache = $cache['slots'] ?? [];

        if (isset($slotsCache[$cacheKey])) {
            return $this->loadedScheduledSlotsByDate = $slotsCache[$cacheKey];
        }

        $slots = TrainingProgramSlot::query()
            ->with([
                'exercises.sets.values',
            ])
            ->where('training_program_id', $this->scheduledTrainingProgramId)
            ->where('user_id', $this->userId)
            ->whereBetween('datetime', [$start, $end])
            ->orderBy('datetime')
            ->orderBy('id')
            ->get()
            ->filter(fn (TrainingProgramSlot $slot): bool => isset($dateLookup[$this->slotDateKey($slot)]))
            ->groupBy(fn (TrainingProgramSlot $slot): string => $this->slotDateKey($slot))
            ->map(fn ($slots): ?TrainingProgramSlot => $slots->first())
            ->filter()
            ->all();

        $slotsCache[$cacheKey] = $slots;
        $cache['slots'] = $slotsCache;

        return $this->loadedScheduledSlotsByDate = $slots;
    }

    protected function resolveScheduledSnapshotsByDate(): array
    {
        if (is_array($this->loadedScheduledSnapshotsByDate)) {
            return $this->loadedScheduledSnapshotsByDate;
        }

        if ($this->scheduledTrainingProgramId === null || $this->userId === null) {
            return $this->loadedScheduledSnapshotsByDate = [];
        }

        $dates = $this->scheduledSessionDates();

        if ($dates === []) {
            return $this->loadedScheduledSnapshotsByDate = [];
        }

        $cacheKey = $this->sharedScheduledDataCacheKey($dates);
        $cache = self::scheduledDataCache();
        $snapshotsCache = $cache['snapshots'] ?? [];

        if (isset($snapshotsCache[$cacheKey])) {
            return $this->loadedScheduledSnapshotsByDate = $snapshotsCache[$cacheKey];
        }

        $snapshots = collect($this->resolveScheduledSlotsByDate())
            ->mapWithKeys(fn (TrainingProgramSlot $slot, string $date): array => [
                $date => app(ScheduledSessionSnapshotBuilder::class)->buildPlanGrid($slot),
            ])
            ->all();

        $snapshotsCache[$cacheKey] = $snapshots;
        $cache['snapshots'] = $snapshotsCache;

        return $this->loadedScheduledSnapshotsByDate = $snapshots;
    }

    private function forgetSharedScheduledDataCache(): void
    {
        $dates = $this->scheduledSessionDates();

        if ($this->scheduledTrainingProgramId === null || $this->userId === null || $dates === []) {
            return;
        }

        $cache = self::scheduledDataCache();
        $cacheKey = $this->sharedScheduledDataCacheKey($dates);
        $slotsCache = $cache['slots'] ?? [];
        $snapshotsCache = $cache['snapshots'] ?? [];

        unset($slotsCache[$cacheKey], $snapshotsCache[$cacheKey]);

        $cache['slots'] = $slotsCache;
        $cache['snapshots'] = $snapshotsCache;
    }

    private function sharedScheduledDataCacheKey(array $dates): string
    {
        return implode('|', [
            (int) $this->scheduledTrainingProgramId,
            (int) $this->userId,
            md5(json_encode(array_values($dates))),
        ]);
    }

    /** @return ArrayObject<string, array<string, array>> */
    private static function scheduledDataCache(): ArrayObject
    {
        if (! app()->bound(self::SCHEDULED_DATA_CACHE_KEY)) {
            app()->scoped(self::SCHEDULED_DATA_CACHE_KEY, fn (): ArrayObject => new ArrayObject([
                'slots' => [],
                'snapshots' => [],
            ]));
        }

        return app(self::SCHEDULED_DATA_CACHE_KEY);
    }

    protected function persistGridOverridesFromCopy(array $gridOverrides): void
    {
        $overrides = $this->getCurrentOverrides();
        $overrides->gridOverrides = $gridOverrides;
        $this->saveOverrides($overrides);
        $this->bumpGridRenderVersion();
        unset($this->configFingerprint, $this->previewGrid, $this->resolvedExerciseOverrides, $this->copyBuckets, $this->copyMenuOptions, $this->resetMenuOptions);
    }

    protected function persistResetGridOverrides(array $gridOverrides, array $sessions): void
    {
        $overrides = $this->getCurrentOverrides();
        $overrides->gridOverrides = $gridOverrides;

        if ($this->userId !== null) {
            $keys = collect($sessions)
                ->map(fn (array $session): string => ((int) $session['week']).':'.((int) $session['session']))
                ->all();
            $overrides->ignoredPlanGridOverrideSessions = array_values(array_unique([
                ...$overrides->ignoredPlanGridOverrideSessions,
                ...$keys,
            ]));
        }

        $this->saveOverrides($overrides);
        $this->bumpGridRenderVersion();
        unset($this->configFingerprint, $this->previewGrid, $this->resolvedExerciseOverrides, $this->copyBuckets, $this->copyMenuOptions, $this->resetMenuOptions);
    }

    protected function bumpGridRenderVersion(): void
    {
        $this->gridRenderVersion++;
    }
}
