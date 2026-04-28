<?php

namespace App\Livewire\Training\View;

use App\Data\Exercise\ExerciseConfig;
use App\Data\Exercise\ExerciseSetting;
use App\Data\Exercise\Preview\ExercisePreviewBuilder;
use App\Data\Exercise\Preview\GridOverrides;
use App\Data\Exercise\Preview\OverrideManager;
use App\Data\Exercise\Preview\PreviewGrid;
use App\Data\Exercise\Preview\StrategyOrchestrator;
use App\Data\Exercise\Settings\SetsSetting;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Exercise\Strategies\Sets\DeloadSetsStrategy;
use App\Data\Training\Config\EffectiveExerciseConfig;
use App\Data\Training\Config\ExerciseOverrides;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExercisePlan;
use App\Training\TrainingSessionRebuildDispatcher;
use Coda\Cms\Livewire\Concerns\InteractsWithParentView;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class PlanExerciseGrid extends Component
{
    use InteractsWithParentView;

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
        $config = $this->getPlanConfig();

        if ($this->userId !== null) {
            return $config->userExerciseOverrides($this->userId, $this->programExerciseId);
        }

        return $config->defaultExerciseOverrides($this->programExerciseId);
    }

    protected function getEffectiveStartsAtDate(): ?string
    {
        $config = $this->getPlanConfig();
        $planOverrides = $config->defaultExerciseOverrides($this->programExerciseId);

        if ($this->userId === null) {
            return $planOverrides->startsAtDate;
        }

        $userOverrides = $config->userExerciseOverrides($this->userId, $this->programExerciseId);

        return $userOverrides->startsAtDate ?? $planOverrides->startsAtDate;
    }

    protected function getEffectiveConfig(): array
    {
        $base = $this->getExerciseConfig();
        $config = $this->getPlanConfig();
        $planOverrides = $config->defaultExerciseOverrides($this->programExerciseId);

        if ($this->userId !== null) {
            $userOverrides = $config->userExerciseOverrides($this->userId, $this->programExerciseId);

            return EffectiveExerciseConfig::resolve($base, $planOverrides, $userOverrides);
        }

        return EffectiveExerciseConfig::resolve($base, $planOverrides);
    }

    protected function getBaseGridOverrides(): array
    {
        $base = $this->getExerciseConfig();

        if ($this->userId !== null) {
            $config = $this->getPlanConfig();
            $planOverrides = $config->defaultExerciseOverrides($this->programExerciseId);

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

    protected function getEffectiveCellDefault(string $field, int $weekIndex, int $setIndex): mixed
    {
        $effectiveConfig = $this->getEffectiveConfig();
        $baseOverrides = $this->getBaseGridOverrides();
        $measuredData = $this->getPlanMeasuredData();

        $weeks = $this->weeks;
        $overrides = GridOverrides::fromArrays(
            $baseOverrides['cells'] ?? [],
            $baseOverrides['weeks'] ?? [],
        );
        $orchestrator = new StrategyOrchestrator($effectiveConfig, $measuredData, $weeks, $overrides, $this->planMaxHR, $this->planIatPercent);
        $state = $orchestrator->execute();

        return $state->getResolvedCellValue($field, $weekIndex, $setIndex);
    }

    protected function getEffectiveWeekDefault(string $field, int $weekIndex): mixed
    {
        $effectiveConfig = $this->getEffectiveConfig();
        $baseOverrides = $this->getBaseGridOverrides();
        $overrides = GridOverrides::fromArrays(
            $baseOverrides['cells'] ?? [],
            $baseOverrides['weeks'] ?? [],
        );

        $weekValue = $overrides->getWeekOverrideValue($weekIndex, $field);
        if ($weekValue !== null) {
            return $weekValue;
        }

        if ($field === 'sets') {
            $strategy = new DeloadSetsStrategy(SetsSetting::from($effectiveConfig['sets'] ?? []));

            return $strategy->getSetsForWeek($weekIndex);
        }

        return $effectiveConfig[$field]['default'] ?? null;
    }

    #[Computed]
    public function isDisabled(): bool
    {
        $config = $this->getPlanConfig();
        $planOverrides = $config->defaultExerciseOverrides($this->programExerciseId);

        if ($this->userId !== null) {
            $userOverrides = $config->userExerciseOverrides($this->userId, $this->programExerciseId);

            return EffectiveExerciseConfig::resolveDisabled($planOverrides, $userOverrides);
        }

        return $planOverrides->disabled ?? false;
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
        unset($this->isDisabled, $this->isDisabledByDefault, $this->configFingerprint, $this->previewGrid);
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

        $overrides = GridOverrides::fromArrays(
            $effectiveConfig['overrides']['cells'] ?? [],
            $effectiveConfig['overrides']['weeks'] ?? [],
        );

        $config = $this->getPlanConfig();
        $planDefaults = $config->defaultExerciseOverrides($this->programExerciseId);
        $originalPlanOverrides = $planDefaults->baselineGridOverrides ?? ['cells' => [], 'weeks' => []];
        $originalEffective = EffectiveExerciseConfig::mergeGridOverrides(
            $this->getExerciseConfig()->overrides,
            $originalPlanOverrides,
        );
        $diffed = $this->diffGridOverrides(
            $effectiveConfig['overrides'] ?? ['cells' => [], 'weeks' => []],
            $originalEffective,
        );
        $highlightOverrides = GridOverrides::fromArrays(
            $diffed['cells'] ?? [],
            $diffed['weeks'] ?? [],
        );

        $grid = ExercisePreviewBuilder::build(
            $effectiveConfig,
            $measuredData,
            $this->weeks,
            $overrides,
            $this->sessionsPerWeek,
            $highlightOverrides,
            $this->planMaxHR,
            $this->planIatPercent,
            $this->getEffectiveStartsAtDate(),
            $this->weekSessionDates,
            $this->lockedSessionsByWeek,
        );

        return $this->clearLockedWeekHighlights($grid);
    }

    /** @return array{cells: array, weeks: array} */
    protected function diffGridOverrides(array $current, ?array $baseline): array
    {
        if ($baseline === null) {
            return $current;
        }

        $baselineCellKeys = [];
        foreach ($baseline['cells'] ?? [] as $cell) {
            $key = $cell['week'].'-'.($cell['session'] ?? 0).'-'.$cell['set'];
            $baselineCellKeys[$key] = $cell['data'] ?? [];
        }

        $baselineWeekKeys = [];
        foreach ($baseline['weeks'] ?? [] as $week) {
            $baselineWeekKeys[(string) $week['week']] = $week['data'] ?? [];
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

        $diffWeeks = [];
        foreach ($current['weeks'] ?? [] as $week) {
            $key = (string) $week['week'];
            if (! isset($baselineWeekKeys[$key])) {
                $diffWeeks[] = $week;
            } else {
                $newData = array_diff_assoc($week['data'] ?? [], $baselineWeekKeys[$key]);
                if (! empty($newData)) {
                    $diffWeeks[] = array_merge($week, ['data' => $newData]);
                }
            }
        }

        return ['cells' => $diffCells, 'weeks' => $diffWeeks];
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
            foreach ($column->overrides as $week => $isOverridden) {
                if ($lockedWeeks[$week] ?? false) {
                    $column->overrides[$week] = false;
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
        $effectiveDefault = $this->getEffectiveCellDefault($field, $weekIndex, $setIndex);

        $overrides->gridOverrides = OverrideManager::updateCellOverride(
            $overrides->gridOverrides,
            $this->getEffectiveConfig(),
            $this->weeks,
            $this->sessionsPerWeek,
            $weekIndex,
            $setIndex,
            $field,
            $value,
            $session,
            $applyToAll,
            $effectiveDefault,
        );

        $this->saveOverrides($overrides);
        unset($this->configFingerprint, $this->previewGrid);
    }

    public function updateWeekOverride(int $weekIndex, string $field, mixed $value): void
    {
        $overrides = $this->getCurrentOverrides();
        $effectiveDefault = $this->getEffectiveWeekDefault($field, $weekIndex);

        $overrides->gridOverrides = OverrideManager::updateWeekOverride(
            $overrides->gridOverrides,
            $this->getEffectiveConfig(),
            $weekIndex,
            $field,
            $value,
            $effectiveDefault,
        );

        $this->saveOverrides($overrides);
        unset($this->configFingerprint, $this->previewGrid);
    }

    public function copyWeek(int $sourceWeek, int $targetWeek): void
    {
        $grid = $this->previewGrid;
        $defaultsGrid = $this->buildDefaultsGrid();
        $overrides = $this->getCurrentOverrides();

        $overrides->gridOverrides = OverrideManager::copyWeekOverrides(
            $overrides->gridOverrides ?? OverrideManager::reset(),
            $grid,
            $defaultsGrid,
            $sourceWeek,
            $targetWeek,
        );

        $this->saveOverrides($overrides);
        unset($this->configFingerprint, $this->previewGrid);
    }

    public function copyWeekToAll(int $sourceWeek): void
    {
        $grid = $this->previewGrid;
        $defaultsGrid = $this->buildDefaultsGrid();
        $overrides = $this->getCurrentOverrides();
        $gridOverrides = $overrides->gridOverrides ?? OverrideManager::reset();

        for ($week = 0; $week < $this->weeks; $week++) {
            if ($week !== $sourceWeek) {
                $gridOverrides = OverrideManager::copyWeekOverrides($gridOverrides, $grid, $defaultsGrid, $sourceWeek, $week);
            }
        }

        $overrides->gridOverrides = $gridOverrides;
        $this->saveOverrides($overrides);
        unset($this->configFingerprint, $this->previewGrid);
    }

    protected function buildDefaultsGrid(): PreviewGrid
    {
        $effectiveConfig = $this->getEffectiveConfig();
        $baseOverrides = $this->getBaseGridOverrides();
        $measuredData = $this->getPlanMeasuredData();

        return ExercisePreviewBuilder::build(
            $effectiveConfig,
            $measuredData,
            $this->weeks,
            GridOverrides::fromArrays($baseOverrides['cells'] ?? [], $baseOverrides['weeks'] ?? []),
            $this->sessionsPerWeek,
            null,
            $this->planMaxHR,
            $this->planIatPercent,
            $this->getEffectiveStartsAtDate(),
            $this->weekSessionDates,
            $this->lockedSessionsByWeek,
        );
    }

    public function resetOverrides(): void
    {
        $overrides = $this->getCurrentOverrides();
        $overrides->gridOverrides = OverrideManager::reset();

        $this->saveOverrides($overrides);
        unset($this->configFingerprint, $this->previewGrid);
    }

    #[On('plan-overrides-reset')]
    public function onPlanOverridesReset(): void
    {
        unset($this->configFingerprint, $this->previewGrid);
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

    protected function getParentConfig(): array
    {
        $base = $this->getExerciseConfig();

        if ($this->userId !== null) {
            $config = $this->getPlanConfig();
            $planOverrides = $config->defaultExerciseOverrides($this->programExerciseId);

            return EffectiveExerciseConfig::resolve($base, $planOverrides);
        }

        return $base->toArray();
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
        unset($this->configFingerprint, $this->previewGrid, $this->settingBadges);
    }

    protected function saveOverrides(ExerciseOverrides $overrides): void
    {
        $this->freezeLockedWeeks($overrides, $this->previewGrid);
        $this->applyFutureOnlyBoundary($overrides);

        $exercisePlan = $this->planType::findOrFail($this->exercisePlanId);
        $config = $exercisePlan->config;

        if ($this->userId !== null) {
            $config->setUserExerciseOverrides($this->userId, $this->programExerciseId, $overrides);
        } else {
            $config->setDefaultExerciseOverrides($this->programExerciseId, $overrides);
        }

        $exercisePlan->config = $config;
        $shouldScopeRebuildToAthlete = $this->userId !== null && $this->planType === ExerciseProgram::class;

        if ($shouldScopeRebuildToAthlete) {
            $exercisePlan->saveQuietly();
            app(TrainingSessionRebuildDispatcher::class)
                ->dispatchFutureSlotsForAthleteExerciseProgram($this->userId, $exercisePlan->id);
        } else {
            $exercisePlan->save();
        }

        $this->dispatch('exercise-overrides-changed');
    }

    protected function freezeLockedWeeks(ExerciseOverrides $overrides, PreviewGrid $grid): void
    {
        $gridOverrides = $overrides->gridOverrides;

        foreach (range(0, $grid->weekCount - 1) as $week) {
            $weekLockedSessions = $this->lockedSessionsByWeek[$week] ?? [];

            if (! in_array(true, $weekLockedSessions, true)) {
                continue;
            }

            foreach ($grid->rows as $row) {
                foreach ($row->cells[$week] ?? [] as $set => $value) {
                    if ($value === null || $value === '' || $value === '-' || $value === '—') {
                        continue;
                    }

                    foreach ($weekLockedSessions as $session => $isLocked) {
                        if (! $isLocked) {
                            continue;
                        }

                        $gridOverrides = $this->putCellOverride($gridOverrides, $week, $session, (int) $set, $row->field, $value);
                    }
                }
            }

            foreach ($grid->weekColumns as $column) {
                $value = $column->cells[$week] ?? null;

                if ($value === null || $value === '' || $value === '-' || $value === '—') {
                    continue;
                }

                $gridOverrides = $this->putWeekOverride($gridOverrides, $week, $column->field, $value);
            }
        }

        $overrides->gridOverrides = $gridOverrides;
    }

    protected function applyFutureOnlyBoundary(ExerciseOverrides $overrides): void
    {
        $boundaryDate = $this->firstUnlockedSessionDate();

        if ($boundaryDate === null) {
            return;
        }

        if ($overrides->startsAtDate === null || $overrides->startsAtDate < $boundaryDate) {
            $overrides->startsAtDate = $boundaryDate;
        }
    }

    protected function firstUnlockedSessionDate(): ?string
    {
        foreach ($this->weekSessionDates as $weekIndex => $sessionDates) {
            $lockedSessions = $this->lockedSessionsByWeek[$weekIndex] ?? [];

            foreach ($sessionDates as $sessionIndex => $sessionDate) {
                if (($lockedSessions[$sessionIndex] ?? false) || ! is_string($sessionDate) || $sessionDate === '') {
                    continue;
                }

                return $sessionDate;
            }
        }

        return null;
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

    protected function putWeekOverride(array $gridOverrides, int $week, string $field, mixed $value): array
    {
        foreach ($gridOverrides['weeks'] ?? [] as $index => $override) {
            if (($override['week'] ?? null) === $week) {
                $gridOverrides['weeks'][$index]['data'][$field] = $value;

                return $gridOverrides;
            }
        }

        $gridOverrides['weeks'][] = [
            'week' => $week,
            'data' => [$field => $value],
        ];

        return $gridOverrides;
    }

    public function render()
    {
        return view('livewire.training.view.plan-exercise-grid');
    }
}
