<?php

namespace App\Livewire\Concerns;

use App\Data\Exercise\DropSet;
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

    public array $expandedPreviewGroups = [];

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
        $hasAutomaticWeight = ! DropSet::isEnabled($this->data['config'] ?? [])
            && in_array('weight', $this->data['config']['settings'] ?? [])
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

    /**
     * @return array{config: array<string, mixed>, preview: array<string, mixed>}
     */
    protected function applyPreviewGridContextOverrides(array $config, array $preview): array
    {
        return [
            'config' => $config,
            'preview' => $preview,
        ];
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
        $contextOverrides = $this->applyPreviewGridContextOverrides($config, $resolvedPreview);
        $config = $contextOverrides['config'];
        $resolvedPreview = $contextOverrides['preview'];
        $config['preview'] = $resolvedPreview;
        $weeks = (int) ($resolvedPreview['weeks'] ?? $this->defaultWeeks);
        $sessionsPerWeek = SessionGroupingMode::resolvePreviewSessionCount($resolvedPreview, $this->defaultSessionsPerWeek);

        $grid = ExercisePreviewBuilder::build($config, $measuredData, $weeks, $overrides, $sessionsPerWeek);
        $expandedIndexes = $this->resolvedExpandedGroupIndexes($grid, $resolvedPreview);
        $forcedExpandedIndexes = $this->forcedExpandedGroupIndexes($grid, $grid->groups);

        foreach ($grid->groups as $group) {
            $group->forceExpanded = in_array($group->index, $forcedExpandedIndexes, true);
            $group->collapsible = $group->sessionCount > 1 && ! $group->forceExpanded;
            $group->expanded = in_array($group->index, $expandedIndexes, true) || $group->forceExpanded;
        }

        $grid->autoCopyValuesAutomatically = false;

        return $grid;
    }

    #[Computed]
    public function effectiveExpandedWeeks(): array
    {
        $config = $this->data['config'] ?? [];
        $preview = $config['preview'] ?? [];
        $grouping = $this->resolveDefaultPreviewGrouping();
        $resolvedPreview = array_merge($preview, [
            'groupingMode' => $preview['groupingMode'] ?? $grouping['mode'],
            'groupSize' => $preview['groupSize'] ?? $grouping['groupSize'],
            'copyValuesAutomatically' => $preview['copyValuesAutomatically'] ?? $grouping['copyValuesAutomatically'],
        ]);
        $contextOverrides = $this->applyPreviewGridContextOverrides($config, $resolvedPreview);
        $resolvedPreview = $contextOverrides['preview'];

        return $this->resolvedExpandedGroupIndexes($this->previewGrid, $resolvedPreview);
    }

    public function toggleExpandedGroup(int $groupIndex): void
    {
        if (in_array($groupIndex, $this->forcedExpandedGroupIndexes($this->previewGrid, $this->previewGrid->groups), true)) {
            return;
        }

        if (in_array($groupIndex, $this->expandedPreviewGroups, true)) {
            $this->expandedPreviewGroups = array_values(array_filter($this->expandedPreviewGroups, fn (int $index): bool => $index !== $groupIndex));
        } else {
            $this->expandedPreviewGroups[] = $groupIndex;
            $this->expandedPreviewGroups = array_values(array_unique($this->expandedPreviewGroups));
        }

        unset($this->previewGrid, $this->effectiveExpandedWeeks, $this->copyBuckets, $this->copyMenuOptions, $this->resetMenuOptions);
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

        unset($this->previewGrid, $this->effectiveExpandedWeeks);
    }

    public function updateSessionOverride(int $weekIndex, int $session, string $field, mixed $value, bool $applyToAll = false): void
    {
        $overrides = $this->data['config']['overrides'] ?? OverrideManager::reset();
        $targets = $applyToAll
            ? $this->previewFanoutTargets($weekIndex, $session)
            : [['week' => $weekIndex, 'session' => $session]];

        foreach ($targets as $target) {
            $effectiveDefault = collect($this->buildDefaultsGrid()->weekColumns)
                ->firstWhere('field', $field)?->getCellValue($target['week'], 0, $target['session']);

            $overrides = OverrideManager::updateSessionOverride(
                $overrides,
                $this->data['config'],
                $target['week'],
                $target['session'],
                $field,
                $value,
                $effectiveDefault,
            );
        }

        $this->data['config']['overrides'] = $overrides;

        unset($this->previewGrid, $this->effectiveExpandedWeeks);
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
        $contextOverrides = $this->applyPreviewGridContextOverrides($config, $resolvedPreview);
        $config = $contextOverrides['config'];
        $resolvedPreview = $contextOverrides['preview'];
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

        $grid->autoCopyValuesAutomatically = false;

        return $grid;
    }

    public function resetOverrides(): void
    {
        $this->data['config']['overrides'] = OverrideManager::reset();
        unset($this->previewGrid, $this->effectiveExpandedWeeks);
    }

    public function updatedDataConfigSettings(): void
    {
        unset($this->fieldsets);
        unset($this->previewGrid, $this->effectiveExpandedWeeks);
        $settings = $this->data['config']['settings'];
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);
        $this->data['config']['settings'] = $settings;
        $this->data['config']['overrides'] = OverrideManager::reset();
        $this->data['config']['preview']['weeks'] = $this->resolveDefaultWeeks();
    }

    public function updatedDataConfigWeightMode(): void
    {
        $this->normalizeDropSetModes();
        unset($this->previewGrid, $this->effectiveExpandedWeeks);
        $this->data['config']['preview']['weeks'] = $this->resolveDefaultWeeks();
    }

    public function updatedDataConfigSetsType(): void
    {
        $this->normalizeDropSetModes();
        unset($this->fieldsets);
        unset($this->previewGrid, $this->effectiveExpandedWeeks);
        $this->data['config']['preview']['weeks'] = $this->resolveDefaultWeeks();
    }

    public function updatedDataConfigPreview(): void
    {
        unset($this->previewGrid, $this->effectiveExpandedWeeks);
    }

    protected function normalizeDropSetModes(): void
    {
        if (! DropSet::isEnabled($this->data['config'] ?? [])) {
            return;
        }

        if (isset($this->data['config']['reps']) && is_array($this->data['config']['reps'])) {
            $this->data['config']['reps']['mode'] = 'manual';
        }

        if (isset($this->data['config']['weight']) && is_array($this->data['config']['weight'])) {
            $this->data['config']['weight']['mode'] = 'manual';
            $this->data['config']['weight']['oneRepMaxModifier'] = null;
        }
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

    protected function groupHasSessionDivergence(PreviewGrid $grid, mixed $group): bool
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
        unset($this->previewGrid, $this->effectiveExpandedWeeks, $this->copyBuckets, $this->copyMenuOptions, $this->resetMenuOptions);
    }

    /** @param array<string, mixed> $resolvedPreview
     *  @return int[]
     */
    protected function resolvedExpandedGroupIndexes(PreviewGrid $grid, array $resolvedPreview): array
    {
        $groups = SessionGroupBuilder::build(
            weekCount: $grid->weekCount,
            sessionCounts: $grid->weekSessionCounts,
            groupingMode: (string) ($resolvedPreview['groupingMode'] ?? SessionGroupingMode::defaultMode()),
            groupSize: SessionGroupingMode::normalizeGroupSize(
                (int) ($resolvedPreview['groupSize'] ?? SessionGroupingMode::defaultGroupSize()),
                (string) ($resolvedPreview['groupingMode'] ?? SessionGroupingMode::defaultMode()),
            ),
        )['groups'];

        return collect(array_merge(
            array_map(static fn ($index): int => (int) $index, $this->expandedPreviewGroups),
            $this->forcedExpandedGroupIndexes($grid, $groups),
        ))->unique()->values()->all();
    }

    /** @param array<int, mixed> $groups
     *  @return int[]
     */
    protected function forcedExpandedGroupIndexes(PreviewGrid $grid, array $groups): array
    {
        return collect($groups)
            ->filter(fn ($group): bool => $this->groupHasSessionDivergence($grid, $group))
            ->map(fn ($group): int => (int) $group->index)
            ->values()
            ->all();
    }
}
