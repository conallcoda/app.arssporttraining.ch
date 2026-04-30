<?php

namespace App\Livewire\Concerns;

use App\Data\Exercise\Preview\ExercisePreviewBuilder;
use App\Data\Exercise\Preview\GridOverrides;
use App\Data\Exercise\Preview\OverrideManager;
use App\Data\Exercise\Preview\PreviewGrid;
use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Data\Exercise\Settings\SetsSetting;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Exercise\Strategies\Sets\DeloadSetsStrategy;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

trait InteractsWithPreview
{
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
     * @return array{mode: string, groupSize: int}
     */
    protected function resolveDefaultPreviewGrouping(): array
    {
        $user = Auth::user();

        return [
            'mode' => (string) ($user?->config->get('settings.session_grouping.mode', SessionGroupingMode::Week->value) ?? SessionGroupingMode::Week->value),
            'groupSize' => max(1, (int) ($user?->config->get('settings.session_grouping.groupSize', 4) ?? 4)),
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

        $weeks = (int) ($preview['weeks'] ?? $this->defaultWeeks);
        $sessionsPerWeek = (int) ($preview['sessionsPerWeek'] ?? $this->defaultSessionsPerWeek);
        $grouping = $this->resolveDefaultPreviewGrouping();
        $config['preview'] = array_merge($preview, [
            'groupingMode' => $grouping['mode'],
            'groupSize' => $grouping['groupSize'],
        ]);

        return ExercisePreviewBuilder::build($config, $measuredData, $weeks, $overrides, $sessionsPerWeek);
    }

    #[Computed]
    public function effectiveExpandedWeeks(): array
    {
        $expanded = [];

        foreach (range(0, $this->previewGrid->weekCount - 1) as $week) {
            if ($this->weekHasSessionDivergence($this->previewGrid, $week)) {
                $expanded[] = $week;
            }
        }

        return $expanded;
    }

    public function updateCellOverride(int $weekIndex, int $setIndex, string $field, mixed $value, int $session, bool $applyToAll = false): void
    {
        $this->data['config']['overrides'] = OverrideManager::updateCellOverride(
            $this->data['config']['overrides'] ?? OverrideManager::reset(),
            $this->data['config'],
            $this->defaultWeeks,
            $this->defaultSessionsPerWeek,
            $weekIndex,
            $setIndex,
            $field,
            $value,
            $session,
            false,
            weekSessionCount: $this->previewGrid->weekSessionCounts[$weekIndex] ?? null,
        );

        unset($this->previewGrid);
    }

    public function updateSessionOverride(int $weekIndex, int $session, string $field, mixed $value): void
    {
        $effectiveDefault = null;

        if ($field === 'sets') {
            $grouping = $this->resolveDefaultPreviewGrouping();
            $sessionCounts = $this->previewGrid->weekSessionCounts;
            $strategy = new DeloadSetsStrategy(
                SetsSetting::from($this->data['config']['sets'] ?? []),
                groupingMode: $grouping['mode'],
                groupSize: $grouping['groupSize'],
                sessionCounts: $sessionCounts,
            );
            $groupMap = \App\Data\Exercise\Preview\SessionGroupBuilder::buildStrategyMap(
                $this->previewGrid->weekCount,
                $sessionCounts,
                $grouping['mode'],
                $grouping['groupSize'],
            );
            $effectiveDefault = $strategy->getSetsForGroup($groupMap['groupIndexByWeekSession'][$weekIndex][$session] ?? $weekIndex);
        }

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

        $weeks = (int) ($preview['weeks'] ?? $this->defaultWeeks);
        $sessionsPerWeek = (int) ($preview['sessionsPerWeek'] ?? $this->defaultSessionsPerWeek);

        return ExercisePreviewBuilder::build(
            $config,
            $measuredData,
            $weeks,
            GridOverrides::fromConfig(OverrideManager::reset()),
            $sessionsPerWeek,
        );
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
}
