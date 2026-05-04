<?php

namespace App\Livewire\Training\View;

use App\Data\Exercise\ExerciseConfig;
use App\Data\Exercise\ExerciseSetting;
use App\Data\Exercise\Preview\ExercisePreviewBuilder;
use App\Data\Exercise\Preview\GridOverrides;
use App\Data\Exercise\Preview\OverrideManager;
use App\Data\Exercise\Preview\PreviewGrid;
use App\Data\Exercise\Preview\SessionGroupingConfig;
use App\Data\Exercise\Preview\SessionGroupBuilder;
use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Training\Config\ResolvedExerciseOverrides;
use App\Data\Training\Config\EffectiveExerciseConfig;
use App\Data\Training\Config\ExerciseOverrides;
use App\Livewire\Concerns\InteractsWithDisplayGridCopying;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExercisePlan;
use App\Training\TrainingSessionRebuildDispatcher;
use Coda\Cms\Livewire\Concerns\InteractsWithParentView;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class PlanExerciseGrid extends Component
{
    use InteractsWithParentView;
    use InteractsWithDisplayGridCopying;

    public int $exercisePlanId;

    public string $planType = ExercisePlan::class;

    public int $programExerciseId;

    public int $exerciseId;

    public ?int $userId = null;

    public int $weeks;

    public int $sessionsPerWeek;

    public array $weekLabels = [];

    public array $weekSessions = [];

    public array $weekSessionDates = [];

    public array $expandedWeeks = [];

    public array $lockedSessionsByWeek = [];

    public bool $sessionLabels = false;

    public string $exerciseName = '';

    #[Reactive]
    public ?string $groupLabel = null;

    public array $exerciseConfigArray = [];

    /** @var array<int, array{label: string, color: string}> */
    public array $exerciseBadges = [];

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
        int $exercisePlanId,
        int $programExerciseId,
        int $exerciseId,
        ?int $userId,
        int $weeks,
        int $sessionsPerWeek,
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
        ?string $groupLabel = null,
    ): void {
        $this->exercisePlanId = $exercisePlanId;
        $this->programExerciseId = $programExerciseId;
        $this->exerciseId = $exerciseId;
        $this->userId = $userId;
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
        $this->expandedWeeks = $expandedWeeks;
        $this->lockedSessionsByWeek = $lockedSessionsByWeek;
        $this->sessionLabels = $sessionLabels;
        $this->groupLabel = $groupLabel;

        $exercise = Exercise::with(['equipment', 'modifiers'])->findOrFail($exerciseId);
        $this->exerciseName = $exercise->name;
        $this->exerciseConfigArray = $exercise->config->toArray();
        $this->exerciseBadges = $this->buildExerciseBadges($exercise);
    }

    protected function getPlanConfig()
    {
        return $this->planType::findOrFail($this->exercisePlanId)->config;
    }

    protected function getExerciseConfig(): ExerciseConfig
    {
        return ExerciseConfig::from($this->exerciseConfigArray);
    }

    protected function getCurrentOverrides(): ExerciseOverrides
    {
        return $this->getPlanConfig()->exerciseOverrides($this->programExerciseId, $this->userId);
    }

    protected function getEffectiveStartsAtDate(): ?string
    {
        return null;
    }

    protected function getEffectiveConfig(): array
    {
        return $this->withResolvedPreviewGrouping($this->resolvedExerciseOverrides->effectiveConfig);
    }

    protected function getBaseGridOverrides(): array
    {
        $base = $this->getExerciseConfig();

        if ($this->userId !== null) {
            $planOverrides = $this->resolvedExerciseOverrides->defaultOverrides;

            return EffectiveExerciseConfig::mergeGridOverrides($base->overrides, $planOverrides->gridOverrides);
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

        $this->saveOverrides($overrides);
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
        if (! $this->requiresMeasuredData || $this->userId === null) {
            return false;
        }

        return ! $this->hasMeasuredData;
    }

    /** @return array{label: string, color: string|null, overridden: bool} */
    #[Computed]
    public function groupingBadge(): array
    {
        $override = $this->getCurrentOverrides()->sessionGrouping;

        if ($override === null) {
            return [
                'label' => 'Default Grouping',
                'color' => null,
                'overridden' => false,
            ];
        }

        $grouping = $override instanceof SessionGroupingConfig ? $override : SessionGroupingConfig::from($override);

        return [
            'label' => match ($grouping->mode) {
                SessionGroupingMode::None->value => 'Ungrouped',
                SessionGroupingMode::Week->value => 'Grouped By Weeks ('.$grouping->groupSize.')',
                default => 'Grouped By Sessions ('.$grouping->groupSize.')',
            },
            'color' => 'green',
            'overridden' => true,
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
        return md5(json_encode($this->getEffectiveConfig()));
    }

    #[Computed]
    public function previewGrid(): PreviewGrid
    {
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
        );

        return $this->clearLockedWeekHighlights($grid);
    }

    #[Computed]
    public function displayGrid(): PreviewGrid
    {
        $grid = $this->previewGrid;
        $expandedWeekLookup = collect($this->effectiveExpandedWeeks)
            ->map(fn (mixed $week): int => (int) $week)
            ->all();
        $effectiveConfig = $this->getEffectiveConfig();
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
        $grid->groupColumnLabel = $grouping['columnLabel'];
        $grid->showGroupColumn = SessionGroupingMode::shouldShowGroupColumn(
            $effectiveConfig['preview']['groupingMode'] ?? null,
            $effectiveConfig['preview']['groupSize'] ?? null,
            count($grouping['groups']),
        );
        $grid->weeks = $grouping['groups'];
        $grid->showWeekColumn = $grid->showGroupColumn;
        $grid->showSessionColumn = true;
        $grid->showCopyMenu = true;
        $grid->autoCopyValuesAutomatically = SessionGroupingMode::shouldAutoCopyValues($effectiveConfig['preview'] ?? []);

        return $grid;
    }

    /** @return array{cells: array, weeks: array} */
    protected function getHistoricalGridOverrides(): array
    {
        $resolvedOverrides = $this->resolvedExerciseOverrides;
        $planOverrides = $resolvedOverrides->defaultOverrides;
        $historicalOverrides = $planOverrides->historicalGridOverrides;

        if ($resolvedOverrides->userOverrides !== null) {
            $historicalOverrides = EffectiveExerciseConfig::mergeGridOverrides(
                $historicalOverrides,
                $resolvedOverrides->userOverrides->historicalGridOverrides,
            );
        }

        return $historicalOverrides;
    }

    #[Computed]
    public function resolvedExerciseOverrides(): ResolvedExerciseOverrides
    {
        return $this->getPlanConfig()->resolveExercise($this->getExerciseConfig(), $this->programExerciseId, $this->userId);
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

        if ($groupingMode === SessionGroupingMode::Groups->value) {
            return array_map(
                static fn ($group): int => (int) $group->index,
                SessionGroupBuilder::build(
                    weekCount: $this->previewGrid->weekCount,
                    sessionCounts: $this->previewGrid->weekSessionCounts,
                    groupingMode: $groupingMode,
                    groupSize: $groupSize,
                    lockedSessionsByWeek: $this->lockedSessionsByWeek,
                    sessionLabels: $this->sessionLabels,
                )['groups'],
            );
        }

        return range(0, max($this->previewGrid->weekCount - 1, 0));
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

    /**
     * @return array{mode: string, groupSize: int, copyValuesAutomatically: bool}
     */
    protected function resolveDefaultPreviewGrouping(): array
    {
        $stored = $this->getPlanConfig()->resolvedSessionGrouping();

        if ($stored instanceof SessionGroupingConfig) {
            return $stored->toArray();
        }

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

    public function updateCellOverride(int $weekIndex, int $setIndex, string $field, mixed $value, int $session, bool $applyToAll = false): void
    {
        $overrides = $this->getCurrentOverrides();
        $targets = $applyToAll
            ? $this->fanoutTargetsForSession($weekIndex, $session)
            : [['week' => $weekIndex, 'session' => $session]];

        foreach ($targets as $target) {
            if ($this->isSessionLocked($target['week'], $target['session'])) {
                continue;
            }

            $overrides->gridOverrides = OverrideManager::updateCellOverride(
                $overrides->gridOverrides,
                $this->getEffectiveConfig(),
                $this->weeks,
                $this->sessionsPerWeek,
                $target['week'],
                $setIndex,
                $field,
                $value,
                $target['session'],
                false,
                $this->getEffectiveCellDefault($field, $target['week'], $setIndex, $target['session']),
                $this->sessionCountForWeek($target['week']),
            );
        }

        $this->saveOverrides($overrides);
        unset($this->configFingerprint, $this->previewGrid, $this->resolvedExerciseOverrides);
    }

    public function updateSessionOverride(int $weekIndex, int $session, string $field, mixed $value): void
    {
        $overrides = $this->getCurrentOverrides();
        $effectiveDefault = $this->getEffectiveSessionDefault($field, $weekIndex, $session);

        $overrides->gridOverrides = OverrideManager::updateSessionOverride(
            $overrides->gridOverrides,
            $this->getEffectiveConfig(),
            $weekIndex,
            $session,
            $field,
            $value,
            $effectiveDefault,
        );

        $this->saveOverrides($overrides);
        unset($this->configFingerprint, $this->previewGrid, $this->resolvedExerciseOverrides);
    }

    protected function buildDefaultsGrid(): PreviewGrid
    {
        $effectiveConfig = $this->getEffectiveConfig();
        $baseOverrides = $this->getBaseGridOverrides();
        $measuredData = $this->getPlanMeasuredData();

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
            $this->resolvedExerciseOverrides->userOverrides,
        );

        return $grid;
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

    protected function isSessionLocked(int $weekIndex, int $sessionIndex): bool
    {
        return (bool) ($this->lockedSessionsByWeek[$weekIndex][$sessionIndex] ?? false);
    }

    public function resetOverrides(): void
    {
        $overrides = $this->getCurrentOverrides();
        $overrides->gridOverrides = OverrideManager::reset();

        $this->saveOverrides($overrides);
        unset($this->configFingerprint, $this->previewGrid, $this->resolvedExerciseOverrides);
    }

    #[On('plan-overrides-reset')]
    public function onPlanOverridesReset(): void
    {
        unset($this->configFingerprint, $this->previewGrid, $this->resolvedExerciseOverrides);
    }

    public function openSettingsForm(?string $focusField = null): void
    {
        $effectiveConfig = $this->getEffectiveConfig();

        $this->dispatch('open-plan-exercise-settings', data: [
            'config' => $effectiveConfig,
            'programExerciseId' => $this->programExerciseId,
            'exerciseId' => $this->exerciseId,
            'userId' => $this->userId,
            'exerciseName' => $this->exerciseName,
            'focusField' => $focusField,
        ]);
    }

    public function openGroupingForm(): void
    {
        $effectiveGrouping = $this->effectiveSessionGrouping()->toArray();

        $this->dispatch('open-plan-exercise-grouping', data: [
            'session_grouping' => $effectiveGrouping,
            'has_override' => $this->getCurrentOverrides()->sessionGrouping !== null,
            'programExerciseId' => $this->programExerciseId,
            'exerciseId' => $this->exerciseId,
            'userId' => $this->userId,
            'exerciseName' => $this->exerciseName,
        ]);
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

    protected function parentSessionGrouping(): SessionGroupingConfig
    {
        $preview = $this->withResolvedPreviewGrouping($this->getParentConfig())['preview'] ?? [];

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

        $settingsConfig = $data['config'] ?? [];
        $parentConfig = $this->getParentConfig();
        $overrides = $this->getCurrentOverrides();

        $overrides->settings = ($settingsConfig['settings'] ?? null) == ($parentConfig['settings'] ?? null)
            ? null
            : ($settingsConfig['settings'] ?? null);

        $formSets = $settingsConfig['sets'] ?? null;
        $parentSets = $parentConfig['sets'] ?? null;
        if (is_array($formSets) && is_array($parentSets)) {
            $formSets = array_merge($parentSets, $formSets);
        }
        $overrides->sets = $formSets == $parentSets
            ? null
            : SetsSetting::from($formSets);

        $settingKeys = ['reps', 'weight', 'tempo', 'rest', 'distance', 'duration', 'heartRate', 'heartRateZone', 'pace', 'watts'];

        foreach ($settingKeys as $key) {
            $formValue = $settingsConfig[$key] ?? null;
            $parentValue = $parentConfig[$key] ?? null;

            if (is_array($formValue) && is_array($parentValue)) {
                $formValue = array_merge($parentValue, $formValue);
            }

            if ($formValue == $parentValue) {
                $overrides->{$key} = null;
            } else {
                $enum = ExerciseSetting::tryFrom($key);
                if ($enum && $settingClass = $enum->settingClass()) {
                    $overrides->{$key} = isset($formValue) ? $settingClass::from($formValue) : null;
                }
            }
        }

        if (isset($settingsConfig['overrides'])) {
            $overrides->gridOverrides = $settingsConfig['overrides'];
        }

        $this->saveOverrides($overrides);
        unset($this->configFingerprint, $this->previewGrid, $this->settingBadges, $this->resolvedExerciseOverrides);
    }

    /** @param array<string, mixed> $data */
    #[On('plan-exercise-grouping.saved')]
    public function onGroupingSaved(array $data): void
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

        $grouping = SessionGroupingConfig::from($data['session_grouping'] ?? []);
        $parentGrouping = $this->parentSessionGrouping();
        $overrides = $this->getCurrentOverrides();

        $overrides->sessionGrouping = $grouping->toArray() === $parentGrouping->toArray()
            ? null
            : $grouping;

        $this->saveOverrides($overrides);
        unset($this->configFingerprint, $this->previewGrid, $this->resolvedExerciseOverrides, $this->copyBuckets, $this->copyMenuOptions, $this->groupingBadge);
    }

    /** @param array<string, mixed> $data */
    #[On('plan-exercise-grouping.reset')]
    public function onGroupingReset(array $data): void
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

        $overrides = $this->getCurrentOverrides();
        $overrides->sessionGrouping = null;

        $this->saveOverrides($overrides);
        unset($this->configFingerprint, $this->previewGrid, $this->resolvedExerciseOverrides, $this->copyBuckets, $this->copyMenuOptions, $this->groupingBadge);
    }

    protected function saveOverrides(ExerciseOverrides $overrides): void
    {
        $this->snapshotLockedWeeks($overrides, $this->previewGrid);

        $exercisePlan = $this->planType::findOrFail($this->exercisePlanId);
        $config = $exercisePlan->config;
        $config->setExerciseOverrides($this->programExerciseId, $overrides, $this->userId);

        $exercisePlan->config = $config;
        $shouldScopeRebuildToAthlete = $this->userId !== null && $this->planType === ExerciseProgram::class;

        if ($shouldScopeRebuildToAthlete) {
            $exercisePlan->saveQuietly();
            app(TrainingSessionRebuildDispatcher::class)
                ->dispatchFutureSlotsForExerciseProgramChange($exercisePlan->id, $this->userId);
        } else {
            $exercisePlan->save();
        }

        $this->dispatch('exercise-overrides-changed');
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

    protected function sessionCountForWeek(int $weekIndex): int
    {
        $explicitSessions = (int) ($this->weekSessions[$weekIndex] ?? 0);
        $datedSessions = count($this->weekSessionDates[$weekIndex] ?? []);
        $lockedSessions = count($this->lockedSessionsByWeek[$weekIndex] ?? []);

        if ($explicitSessions > 0 || $datedSessions > 0 || $lockedSessions > 0) {
            return max($explicitSessions, $datedSessions, $lockedSessions, 1);
        }

        return $this->effectivePreviewSessionsPerWeek($this->getEffectiveConfig());
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

        if (collect($grid->rows)->contains(fn ($row): bool => (bool) $row->lastSessionOnly)) {
            return true;
        }

        $baseline = $sessions[0] ?? null;
        if ($baseline === null) {
            return false;
        }

        foreach ($grid->rows as $row) {
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

    public function render()
    {
        return view('livewire.training.view.plan-exercise-grid');
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

    protected function persistGridOverridesFromCopy(array $gridOverrides): void
    {
        $overrides = $this->getCurrentOverrides();
        $overrides->gridOverrides = $gridOverrides;
        $this->saveOverrides($overrides);
        unset($this->configFingerprint, $this->previewGrid, $this->resolvedExerciseOverrides, $this->copyBuckets, $this->copyMenuOptions);
    }
}
