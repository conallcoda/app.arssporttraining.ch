<?php

namespace App\Livewire\Concerns;

use App\Data\Exercise\Preview\ExercisePreviewBuilder;
use App\Data\Exercise\Preview\GridOverrides;
use App\Data\Exercise\Preview\OverrideManager;
use App\Data\Exercise\Preview\PreviewGrid;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use Livewire\Attributes\Computed;

trait InteractsWithPreview
{
    public int $defaultWeeks = 5;

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

    protected function applyPreviewDefaults(): void
    {
        $this->data['config']['preview'] = array_merge($this->data['config']['preview'] ?? [], [
            'weeks' => $this->defaultWeeks,
            'sessionsPerWeek' => $this->defaultSessionsPerWeek,
            'measuredReps' => 8,
            'measuredWeight' => 52,
            'targetGoal' => 7,
        ]);
    }

    protected function openPreview(array $data): void
    {
        if (empty($data)) {
            $this->applyPreviewDefaults();
        }
    }

    #[Computed]
    public function previewGrid(): PreviewGrid
    {
        $preview = $this->data['config']['preview'] ?? [];
        $measuredData = new WeightProgressionSetting(
            measuredReps: $preview['measuredReps'] ?? null,
            measuredWeight: $preview['measuredWeight'] ?? null,
            targetGoal: $preview['targetGoal'] ?? null,
        );

        $overrides = GridOverrides::fromArrays(
            $this->data['config']['overrides']['cells'] ?? [],
            $this->data['config']['overrides']['weeks'] ?? [],
        );

        $weeks = (int) ($preview['weeks'] ?? $this->defaultWeeks);
        $sessionsPerWeek = (int) ($preview['sessionsPerWeek'] ?? $this->defaultSessionsPerWeek);

        return ExercisePreviewBuilder::build($this->data['config'], $measuredData, $weeks, $overrides, $sessionsPerWeek);
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
            $applyToAll,
        );

        unset($this->previewGrid);
    }

    public function updateWeekOverride(int $weekIndex, string $field, mixed $value): void
    {
        $this->data['config']['overrides'] = OverrideManager::updateWeekOverride(
            $this->data['config']['overrides'] ?? OverrideManager::reset(),
            $this->data['config'],
            $weekIndex,
            $field,
            $value,
        );

        unset($this->previewGrid);
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
    }

    public function updatedDataConfigPreview(): void
    {
        unset($this->previewGrid);
    }
}
