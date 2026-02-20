<?php

namespace App\Livewire\Training\View;

use App\Data\Exercise\ExerciseConfig;
use App\Data\Exercise\Preview\ExercisePreviewBuilder;
use App\Data\Exercise\Preview\GridOverrides;
use App\Data\Exercise\Preview\OverrideManager;
use App\Data\Exercise\Preview\PreviewGrid;
use App\Data\Exercise\Preview\StrategyOrchestrator;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Training\Config\EffectiveExerciseConfig;
use App\Data\Training\Config\ExerciseOverrides;
use App\Data\Training\Config\TrainingPlanConfig;
use App\Models\Exercise\Exercise;
use App\Models\TrainingPlan;
use Coda\Cms\Livewire\Concerns\InteractsWithParentView;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PlanExerciseGrid extends Component
{
    use InteractsWithParentView;

    public int $trainingPlanId;

    public int $exerciseId;

    public ?int $userId = null;

    public int $weeks;

    public int $sessionsPerWeek;

    public string $exerciseName = '';

    public array $exerciseConfigArray = [];

    /** @var array<int, array{label: string, color: string}> */
    public array $exerciseBadges = [];

    public function mount(
        int $trainingPlanId,
        int $exerciseId,
        ?int $userId,
        int $weeks,
        int $sessionsPerWeek,
    ): void {
        $this->trainingPlanId = $trainingPlanId;
        $this->exerciseId = $exerciseId;
        $this->userId = $userId;
        $this->weeks = $weeks;
        $this->sessionsPerWeek = $sessionsPerWeek;

        $exercise = Exercise::with(['equipment', 'modifiers'])->findOrFail($exerciseId);
        $this->exerciseName = $exercise->name;
        $this->exerciseConfigArray = $exercise->config->toArray();
        $this->exerciseBadges = $this->buildExerciseBadges($exercise);
    }

    protected function getTrainingPlanConfig(): TrainingPlanConfig
    {
        return TrainingPlan::findOrFail($this->trainingPlanId)->config;
    }

    protected function getExerciseConfig(): ExerciseConfig
    {
        return ExerciseConfig::from($this->exerciseConfigArray);
    }

    protected function getCurrentOverrides(): ExerciseOverrides
    {
        $config = $this->getTrainingPlanConfig();

        if ($this->userId !== null) {
            return $config->userExerciseOverrides($this->exerciseId, $this->userId);
        }

        return $config->defaultExerciseOverrides($this->exerciseId);
    }

    protected function getEffectiveConfig(): array
    {
        $base = $this->getExerciseConfig();
        $config = $this->getTrainingPlanConfig();
        $planOverrides = $config->defaultExerciseOverrides($this->exerciseId);

        if ($this->userId !== null) {
            $userOverrides = $config->userExerciseOverrides($this->exerciseId, $this->userId);

            return EffectiveExerciseConfig::resolve($base, $planOverrides, $userOverrides);
        }

        return EffectiveExerciseConfig::resolve($base, $planOverrides);
    }

    protected function getBaseGridOverrides(): array
    {
        $base = $this->getExerciseConfig();

        if ($this->userId !== null) {
            $config = $this->getTrainingPlanConfig();
            $planOverrides = $config->defaultExerciseOverrides($this->exerciseId);

            return EffectiveExerciseConfig::mergeGridOverrides($base->overrides, $planOverrides->gridOverrides);
        }

        return $base->overrides;
    }

    protected function getEffectiveCellDefault(string $field, int $weekIndex, int $setIndex): mixed
    {
        $effectiveConfig = $this->getEffectiveConfig();
        $baseOverrides = $this->getBaseGridOverrides();
        $preview = $effectiveConfig['preview'] ?? [];
        $measuredData = new WeightProgressionSetting(
            measuredReps: $preview['measuredReps'] ?? null,
            measuredWeight: $preview['measuredWeight'] ?? null,
            targetGoal: $preview['targetGoal'] ?? null,
        );

        $weeks = (int) ($preview['weeks'] ?? $this->weeks);
        $overrides = GridOverrides::fromArrays(
            $baseOverrides['cells'] ?? [],
            $baseOverrides['weeks'] ?? [],
        );
        $orchestrator = new StrategyOrchestrator($effectiveConfig, $measuredData, $weeks, $overrides);
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

        return $effectiveConfig[$field]['default'] ?? null;
    }

    #[Computed]
    public function previewGrid(): PreviewGrid
    {
        $effectiveConfig = $this->getEffectiveConfig();

        $preview = $effectiveConfig['preview'] ?? [];
        $measuredData = new WeightProgressionSetting(
            measuredReps: $preview['measuredReps'] ?? null,
            measuredWeight: $preview['measuredWeight'] ?? null,
            targetGoal: $preview['targetGoal'] ?? null,
        );

        $overrides = GridOverrides::fromArrays(
            $effectiveConfig['overrides']['cells'] ?? [],
            $effectiveConfig['overrides']['weeks'] ?? [],
        );

        $currentOverrides = $this->getCurrentOverrides();
        $highlightOverrides = GridOverrides::fromArrays(
            $currentOverrides->gridOverrides['cells'] ?? [],
            $currentOverrides->gridOverrides['weeks'] ?? [],
        );

        return ExercisePreviewBuilder::build(
            $effectiveConfig,
            $measuredData,
            $this->weeks,
            $overrides,
            $this->sessionsPerWeek,
            $highlightOverrides,
        );
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
        unset($this->previewGrid);
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
        unset($this->previewGrid);
    }

    public function resetOverrides(): void
    {
        $overrides = $this->getCurrentOverrides();
        $overrides->gridOverrides = OverrideManager::reset();

        $this->saveOverrides($overrides);
        unset($this->previewGrid);
    }

    public function openSettingsForm(): void
    {
        $effectiveConfig = $this->getEffectiveConfig();

        $this->dispatch('open-plan-exercise-settings', data: [
            'config' => $effectiveConfig,
            'exerciseId' => $this->exerciseId,
            'userId' => $this->userId,
            'exerciseName' => $this->exerciseName,
        ]);
    }

    /** @param array<string, mixed> $data */
    #[\Livewire\Attributes\On('plan-exercise-settings.saved')]
    public function onSettingsSaved(array $data): void
    {
        if (($data['exerciseId'] ?? null) !== $this->exerciseId) {
            return;
        }

        if (($data['userId'] ?? null) !== $this->userId) {
            return;
        }

        $settingsConfig = $data['config'] ?? [];
        $overrides = $this->getCurrentOverrides();

        if (isset($settingsConfig['settings'])) {
            $overrides->settings = $settingsConfig['settings'];
        }

        if (isset($settingsConfig['sets'])) {
            $overrides->sets = \App\Data\Exercise\Settings\SetsSetting::from($settingsConfig['sets']);
        }

        $settingKeys = ['reps', 'weight', 'tempo', 'rest', 'distance', 'duration', 'heartRate', 'heartRateZone', 'pace', 'watts'];

        foreach ($settingKeys as $key) {
            if (isset($settingsConfig[$key])) {
                $enum = \App\Data\Exercise\ExerciseSetting::tryFrom($key);
                if ($enum && $settingClass = $enum->settingClass()) {
                    $overrides->{$key} = $settingClass::from($settingsConfig[$key]);
                }
            }
        }

        if (isset($settingsConfig['overrides'])) {
            $overrides->gridOverrides = $settingsConfig['overrides'];
        }

        $this->saveOverrides($overrides);
        unset($this->previewGrid);
    }

    protected function saveOverrides(ExerciseOverrides $overrides): void
    {
        $trainingPlan = TrainingPlan::findOrFail($this->trainingPlanId);
        $config = $trainingPlan->config;

        if ($this->userId !== null) {
            $config->setUserExerciseOverrides($this->exerciseId, $this->userId, $overrides);
        } else {
            $config->setDefaultExerciseOverrides($this->exerciseId, $overrides);
        }

        $trainingPlan->config = $config;
        $trainingPlan->save();

        $this->notifyChanged('config');
    }

    public function render()
    {
        return view('livewire.training.view.plan-exercise-grid');
    }
}
