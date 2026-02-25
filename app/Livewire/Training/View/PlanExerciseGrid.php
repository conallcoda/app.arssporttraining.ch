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
use App\Models\Exercise\Exercise;
use App\Models\TrainingPlan;
use Coda\Cms\Livewire\Concerns\InteractsWithParentView;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class PlanExerciseGrid extends Component
{
    use InteractsWithParentView;

    public int $trainingPlanId;

    public string $planType = TrainingPlan::class;

    public int $exerciseId;

    public ?int $userId = null;

    public int $weeks;

    public int $sessionsPerWeek;

    public string $exerciseName = '';

    public array $exerciseConfigArray = [];

    /** @var array<int, array{label: string, color: string}> */
    public array $exerciseBadges = [];

    #[Reactive]
    public bool $disabled = false;

    #[Reactive]
    public ?int $planMeasuredReps = null;

    #[Reactive]
    public ?float $planMeasuredWeight = null;

    #[Reactive]
    public int|float $planTargetGoal = 10;

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

    protected function getTrainingPlanConfig()
    {
        return $this->planType::findOrFail($this->trainingPlanId)->config;
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
    public function isDisabled(): bool
    {
        $config = $this->getTrainingPlanConfig();
        $planOverrides = $config->defaultExerciseOverrides($this->exerciseId);

        if ($this->userId !== null) {
            $userOverrides = $config->userExerciseOverrides($this->exerciseId, $this->userId);

            return EffectiveExerciseConfig::resolveDisabled($planOverrides, $userOverrides);
        }

        return $planOverrides->disabled ?? false;
    }

    #[Computed]
    public function isDisabledByDefault(): bool
    {
        $config = $this->getTrainingPlanConfig();
        $planOverrides = $config->defaultExerciseOverrides($this->exerciseId);

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

    public function resetOverrides(): void
    {
        $overrides = $this->getCurrentOverrides();
        $overrides->gridOverrides = OverrideManager::reset();

        $this->saveOverrides($overrides);
        unset($this->configFingerprint, $this->previewGrid);
    }

    #[\Livewire\Attributes\On('plan-overrides-reset')]
    public function onPlanOverridesReset(): void
    {
        unset($this->configFingerprint, $this->previewGrid);
    }

    public function openSettingsForm(?string $focusField = null): void
    {
        $effectiveConfig = $this->getEffectiveConfig();

        $this->dispatch('open-plan-exercise-settings', data: [
            'config' => $effectiveConfig,
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
            $config = $this->getTrainingPlanConfig();
            $planOverrides = $config->defaultExerciseOverrides($this->exerciseId);

            return EffectiveExerciseConfig::resolve($base, $planOverrides);
        }

        return $base->toArray();
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
            : \App\Data\Exercise\Settings\SetsSetting::from($formSets);

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
                $enum = \App\Data\Exercise\ExerciseSetting::tryFrom($key);
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
        $trainingPlan = $this->planType::findOrFail($this->trainingPlanId);
        $config = $trainingPlan->config;

        if ($this->userId !== null) {
            $config->setUserExerciseOverrides($this->exerciseId, $this->userId, $overrides);
        } else {
            $config->setDefaultExerciseOverrides($this->exerciseId, $overrides);
        }

        $trainingPlan->config = $config;
        $trainingPlan->save();

        $this->dispatch('exercise-overrides-changed');
    }

    public function render()
    {
        return view('livewire.training.view.plan-exercise-grid');
    }
}
