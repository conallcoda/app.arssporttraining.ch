<?php

namespace App\Livewire\Concerns;

use App\Data\Exercise\Preview\ExercisePreviewBuilder;
use App\Data\Exercise\Preview\GridOverrides;
use App\Data\Exercise\Preview\OverrideManager;
use App\Data\Exercise\Preview\PreviewGrid;
use App\Data\Exercise\Preview\SessionGroupBuilder;
use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

trait InteractsWithPreview
{
    use InteractsWithDisplayGridCopying;

    public int $defaultWeeks = 1;

    public int $defaultSessionsPerWeek = 1;

    public bool $showPreview = true;

    public bool $showData = false;

    public string $activeTab = 'preview';

    protected function initializePreview(
        int $defaultWeeks,
        int $defaultSessionsPerWeek,
        bool $showPreview,
        bool $showData,
    ): void {
        $this->defaultWeeks = $defaultWeeks;
        $this->defaultSessionsPerWeek = $defaultSessionsPerWeek;
        $this->showPreview = $showPreview;
        $this->showData = $showData;
    }

    protected function resolveDefaultWeeks(): int
    {
        $hasAutomaticWeight = in_array('weight', $this->data['config']['settings'] ?? [])
            && ($this->data['config']['weight']['mode'] ?? 'manual') === 'automatic';

        return $hasAutomaticWeight ? 5 : 1;
    }

    protected function applyPreviewDefaults(): void
    {
        $this->data['config']['preview'] = array_merge($this->data['config']['preview'] ?? [], [
            'weeks' => $this->resolveDefaultWeeks(),
            'sessionsPerWeek' => $this->defaultSessionsPerWeek,
            'measuredReps' => 1,
            'measuredWeight' => 50,
            'targetGoal' => 10,
        ]);
    }

    /**
     * @return array{mode: string, groupSize: int, copyValuesAutomatically: bool}
     */
    protected function resolveDefaultPreviewGrouping(): array
    {
        $user = Auth::user();
        $mode = (string) ($user?->config->get('settings.session_grouping.mode', SessionGroupingMode::defaultMode()) ?? SessionGroupingMode::defaultMode());

        return [
            'mode' => $mode,
            'groupSize' => SessionGroupingMode::normalizeGroupSize(
                is_numeric($user?->config->get('settings.session_grouping.groupSize'))
                    ? (int) $user?->config->get('settings.session_grouping.groupSize')
                    : null,
                $mode,
            ),
            'copyValuesAutomatically' => (bool) ($user?->config->get('settings.session_grouping.copyValuesAutomatically', SessionGroupingMode::defaultCopyValuesAutomatically()) ?? SessionGroupingMode::defaultCopyValuesAutomatically()),
        ];
    }

    protected function openPreview(array $data): void
    {
        if (empty($data)) {
            $this->applyPreviewDefaults();

            return;
        }

        if (! empty($data['id'] ?? null)) {
            return;
        }
    }

    #[Computed]
    public function previewGrid(): PreviewGrid
    {
        $config = $this->data['config'] ?? [];
        $preview = $config['preview'] ?? [];
        $measuredData = new WeightProgressionSetting(
            measuredReps: $preview['measuredReps'] ?? null,
            measuredWeight: $preview['measuredWeight'] ?? null,
            targetGoal: $preview['targetGoal'] ?? null,
        );

        $overrides = GridOverrides::fromConfig($config['overrides'] ?? []);

        $grouping = $this->resolveDefaultPreviewGrouping();
        $resolvedPreview = array_merge($preview, [
            'groupingMode' => $preview['groupingMode'] ?? $grouping['mode'],
            'groupSize' => $preview['groupSize'] ?? $grouping['groupSize'],
            'copyValuesAutomatically' => $preview['copyValuesAutomatically'] ?? $grouping['copyValuesAutomatically'],
        ]);
        $config['preview'] = $resolvedPreview;
        $weeks = (int) ($resolvedPreview['weeks'] ?? $this->defaultWeeks);
        $sessionsPerWeek = SessionGroupingMode::resolvePreviewSessionCount($resolvedPreview, $this->defaultSessionsPerWeek);

        $grid = ExercisePreviewBuilder::build($config, $measuredData, $weeks, $overrides, $sessionsPerWeek);
        $grid->autoCopyValuesAutomatically = SessionGroupingMode::shouldAutoCopyValues($resolvedPreview);

        return $grid;
    }

    #[Computed]
    public function effectiveExpandedWeeks(): array
    {
        return range(0, max($this->previewGrid->weekCount - 1, 0));
    }

    public function updateCellOverride(int $weekIndex, int $setIndex, string $field, mixed $value, int $session, bool $applyToAll = false): void
    {
        $overrides = $this->data['config']['overrides'] ?? OverrideManager::reset();
        $targets = $applyToAll
            ? $this->previewFanoutTargets($weekIndex, $session)
            : [['week' => $weekIndex, 'session' => $session]];

        foreach ($targets as $target) {
            $defaultRow = collect($this->buildDefaultsGrid()->rows)->firstWhere('field', $field);

            $overrides = OverrideManager::updateCellOverride(
                $overrides,
                $this->data['config'],
                $this->defaultWeeks,
                $this->defaultSessionsPerWeek,
                $target['week'],
                $setIndex,
                $field,
                $value,
                $target['session'],
                false,
                $defaultRow?->getCellValue($target['week'], $setIndex, $target['session']),
                $this->previewGrid->weekSessionCounts[$target['week']] ?? null,
            );
        }

        $this->data['config']['overrides'] = $overrides;

        unset($this->previewGrid);
    }

    public function updateSessionOverride(int $weekIndex, int $session, string $field, mixed $value): void
    {
        $effectiveDefault = collect($this->buildDefaultsGrid()->weekColumns)
            ->firstWhere('field', $field)?->getCellValue($weekIndex, 0, $session);

        $this->data['config']['overrides'] = OverrideManager::updateSessionOverride(
            $this->data['config']['overrides'] ?? OverrideManager::reset(),
            $this->data['config'],
            $weekIndex,
            $session,
            $field,
            $value,
            $effectiveDefault,
        );

        unset($this->previewGrid);
    }

    protected function buildDefaultsGrid(): PreviewGrid
    {
        $config = $this->data['config'] ?? [];
        $preview = $config['preview'] ?? [];
        $measuredData = new WeightProgressionSetting(
            measuredReps: $preview['measuredReps'] ?? null,
            measuredWeight: $preview['measuredWeight'] ?? null,
            targetGoal: $preview['targetGoal'] ?? null,
        );

        $grouping = $this->resolveDefaultPreviewGrouping();
        $resolvedPreview = array_merge($preview, [
            'groupingMode' => $preview['groupingMode'] ?? $grouping['mode'],
            'groupSize' => $preview['groupSize'] ?? $grouping['groupSize'],
            'copyValuesAutomatically' => $preview['copyValuesAutomatically'] ?? $grouping['copyValuesAutomatically'],
        ]);
        $config['preview'] = $resolvedPreview;
        $weeks = (int) ($resolvedPreview['weeks'] ?? $this->defaultWeeks);
        $sessionsPerWeek = SessionGroupingMode::resolvePreviewSessionCount($resolvedPreview, $this->defaultSessionsPerWeek);

        $grid = ExercisePreviewBuilder::build(
            $config,
            $measuredData,
            $weeks,
            GridOverrides::fromConfig(OverrideManager::reset()),
            $sessionsPerWeek,
        );

        $grid->autoCopyValuesAutomatically = SessionGroupingMode::shouldAutoCopyValues($resolvedPreview);

        return $grid;
    }

    public function resetOverrides(): void
    {
        $this->data['config']['overrides'] = OverrideManager::reset();
        unset($this->previewGrid);
    }

    public function updatedDataConfigSettings(): void
    {
        unset($this->fieldsets);
        unset($this->previewGrid);
        $settings = $this->data['config']['settings'];
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);
        $this->data['config']['settings'] = $settings;
        $this->data['config']['overrides'] = OverrideManager::reset();
        $this->data['config']['preview']['weeks'] = $this->resolveDefaultWeeks();
    }

    public function updatedDataConfigWeightMode(): void
    {
        unset($this->previewGrid);
        $this->data['config']['preview']['weeks'] = $this->resolveDefaultWeeks();
    }

    public function updatedDataConfigPreview(): void
    {
        unset($this->previewGrid);
    }

    protected function weekHasSessionDivergence(PreviewGrid $grid, int $week): bool
    {
        $sessionCount = $grid->weekSessionCounts[$week] ?? $grid->sessionsPerWeek;

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

        return false;
    }

    /** @return array<int, array{week:int, session:int}> */
    protected function previewFanoutTargets(int $weekIndex, int $sessionIndex): array
    {
        $grouping = $this->resolveDefaultPreviewGrouping();
        $strategyMap = SessionGroupBuilder::buildStrategyMap(
            $this->previewGrid->weekCount,
            $this->previewGrid->weekSessionCounts,
            $grouping['mode'],
            $grouping['groupSize'],
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

    protected function displayGridForCopy(): PreviewGrid
    {
        $grid = $this->previewGrid;
        $grid->showCopyMenu = true;

        return $grid;
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
        return $this->data['config']['overrides'] ?? OverrideManager::reset();
    }

    protected function persistGridOverridesFromCopy(array $gridOverrides): void
    {
        $this->data['config']['overrides'] = $gridOverrides;
        unset($this->previewGrid, $this->copyBuckets, $this->copyMenuOptions);
    }
}
