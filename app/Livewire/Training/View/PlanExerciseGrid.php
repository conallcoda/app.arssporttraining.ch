<?php

namespace App\Livewire\Training\View;

use App\Data\Exercise\ExerciseConfig;
use App\Data\Exercise\Preview\ExercisePreviewBuilder;
use App\Data\Exercise\Preview\GridOverrides;
use App\Data\Exercise\Preview\OverrideManager;
use App\Data\Exercise\Preview\PreviewGrid;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Training\Config\EffectiveExerciseConfig;
use App\Data\Training\Config\ExerciseOverrides;
use App\Data\Training\Config\PlanExerciseConfig;
use App\Models\Exercise\Exercise;
use App\Models\TrainingPlanProgramExercise;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PlanExerciseGrid extends Component
{
    public int $pivotId;

    public int $exerciseId;

    public ?int $userId = null;

    public int $weeks;

    public int $sessionsPerWeek;

    public string $exerciseName = '';

    public array $exerciseConfigArray = [];

    public array $pivotConfigArray = [];

    public function mount(
        int $pivotId,
        int $exerciseId,
        ?int $userId,
        int $weeks,
        int $sessionsPerWeek,
    ): void {
        $this->pivotId = $pivotId;
        $this->exerciseId = $exerciseId;
        $this->userId = $userId;
        $this->weeks = $weeks;
        $this->sessionsPerWeek = $sessionsPerWeek;

        $exercise = Exercise::findOrFail($exerciseId);
        $this->exerciseName = $exercise->name;
        $this->exerciseConfigArray = $exercise->config->toArray();

        $pivot = TrainingPlanProgramExercise::findOrFail($pivotId);
        $this->pivotConfigArray = $this->resolvePivotConfig($pivot)->toArray();
    }

    protected function resolvePivotConfig(TrainingPlanProgramExercise $pivot): PlanExerciseConfig
    {
        $config = $pivot->config;

        if ($config instanceof PlanExerciseConfig) {
            return $config;
        }

        return new PlanExerciseConfig;
    }

    protected function getPlanExerciseConfig(): PlanExerciseConfig
    {
        return PlanExerciseConfig::from($this->pivotConfigArray);
    }

    protected function getExerciseConfig(): ExerciseConfig
    {
        return ExerciseConfig::from($this->exerciseConfigArray);
    }

    protected function getCurrentOverrides(): ExerciseOverrides
    {
        $config = $this->getPlanExerciseConfig();

        if ($this->userId !== null) {
            return $config->forUser($this->userId);
        }

        return $config->plan;
    }

    protected function getEffectiveConfig(): array
    {
        $base = $this->getExerciseConfig();
        $planConfig = $this->getPlanExerciseConfig();

        if ($this->userId !== null) {
            return EffectiveExerciseConfig::resolve($base, $planConfig->plan, $planConfig->forUser($this->userId));
        }

        return EffectiveExerciseConfig::resolve($base, $planConfig->plan);
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

        $currentOverrides = $this->getCurrentOverrides();
        $overrides = GridOverrides::fromArrays(
            $currentOverrides->gridOverrides['cells'] ?? [],
            $currentOverrides->gridOverrides['weeks'] ?? [],
        );

        return ExercisePreviewBuilder::build(
            $effectiveConfig,
            $measuredData,
            $this->weeks,
            $overrides,
            $this->sessionsPerWeek,
        );
    }

    #[Computed]
    public function badges(): array
    {
        $effectiveConfig = $this->getEffectiveConfig();
        $badges = [];

        $sets = $effectiveConfig['sets'] ?? [];
        if (! empty($sets['count'])) {
            $badges[] = $sets['count'].' sets';
        }

        foreach ($effectiveConfig['settings'] ?? [] as $setting) {
            $config = $effectiveConfig[$setting] ?? [];
            $default = $config['default'] ?? null;

            if ($default !== null && $default !== '' && $default !== 0) {
                $label = match ($setting) {
                    'reps' => $default.' reps',
                    'tempo' => (string) $default,
                    'rest' => $default.'s',
                    default => ucfirst($setting).': '.$default,
                };
                $badges[] = $label;
            }
        }

        return $badges;
    }

    public function updateCellOverride(int $weekIndex, int $setIndex, string $field, mixed $value, int $session, bool $applyToAll = false): void
    {
        $overrides = $this->getCurrentOverrides();

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
        );

        $this->saveOverrides($overrides);
        unset($this->previewGrid, $this->badges);
    }

    public function updateWeekOverride(int $weekIndex, string $field, mixed $value): void
    {
        $overrides = $this->getCurrentOverrides();

        $overrides->gridOverrides = OverrideManager::updateWeekOverride(
            $overrides->gridOverrides,
            $this->getEffectiveConfig(),
            $weekIndex,
            $field,
            $value,
        );

        $this->saveOverrides($overrides);
        unset($this->previewGrid, $this->badges);
    }

    public function resetOverrides(): void
    {
        $overrides = $this->getCurrentOverrides();
        $overrides->gridOverrides = OverrideManager::reset();

        $this->saveOverrides($overrides);
        unset($this->previewGrid, $this->badges);
    }

    public function openSettingsForm(): void
    {
        $effectiveConfig = $this->getEffectiveConfig();

        $this->dispatch('open-plan-exercise-settings', data: [
            'config' => $effectiveConfig,
            'pivotId' => $this->pivotId,
            'userId' => $this->userId,
            'exerciseName' => $this->exerciseName,
        ]);
    }

    /** @param array<string, mixed> $data */
    #[\Livewire\Attributes\On('plan-exercise-settings.saved')]
    public function onSettingsSaved(array $data): void
    {
        if (($data['pivotId'] ?? null) !== $this->pivotId) {
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

        $settingKeys = ['sets', 'reps', 'weight', 'tempo', 'rest', 'distance', 'duration', 'pace', 'watts'];

        foreach ($settingKeys as $key) {
            if (isset($settingsConfig[$key])) {
                $enum = \App\Data\Exercise\ExerciseSetting::tryFrom($key);
                if ($enum && $settingClass = $enum->settingClass()) {
                    $overrides->{$key} = $settingClass::from($settingsConfig[$key]);
                }
            }
        }

        $this->saveOverrides($overrides);
        unset($this->previewGrid, $this->badges);
    }

    protected function saveOverrides(ExerciseOverrides $overrides): void
    {
        $pivot = TrainingPlanProgramExercise::findOrFail($this->pivotId);
        $config = $this->resolvePivotConfig($pivot);

        if ($this->userId !== null) {
            $config->setUserOverrides($this->userId, $overrides);
        } else {
            $config->plan = $overrides;
        }

        $pivot->config = $config;
        $pivot->save();

        $this->pivotConfigArray = $config->toArray();
    }

    public function render()
    {
        return view('livewire.training.view.plan-exercise-grid');
    }
}
