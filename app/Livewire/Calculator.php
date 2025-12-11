<?php

namespace App\Livewire;

use App\Data\Form\FluxFieldset;
use App\Models\Training\ExercisePlan\AthleteData;
use App\Models\Training\ExercisePlan\AthleteExerciseConfig;
use App\Models\Training\ExercisePlan\AthleteTestData;
use App\Models\Training\ExercisePlan\ExerciseBlockManager;
use App\Models\Training\ExercisePlan\ExerciseData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Training Plan Calculator')]
class Calculator extends Component
{
    #[Url]
    public string $tab = 'calculator';

    public array $athletes = [];

    public array $exercises = [];

    public ExerciseBlockManager $manager;

    public AthleteExerciseConfig $config;

    public int $selectedAthleteId = 1;

    public int $selectedExerciseId = 2;

    public float $targetGoal = 10;

    public string $selectedStrategy = 'fixed_decrement';

    public array $strategyConfig = [];

    public array $initialRulesConfig = [];

    public array $actionRulesConfig = [];

    public array $initialRulesEnabled = [];

    public array $actionRulesEnabled = [];

    public bool $showAdvancedModal = false;

    public function mount(): void
    {
        $this->athletes[] = AthleteData::example();
        $this->athletes[] = AthleteData::strong_doe();

        $this->exercises = [
            ExerciseData::back_squat(),
            ExerciseData::front_squat(),
            ExerciseData::from(['id' => 3, 'name' => 'Deadlift (Wide)', 'modifier' => 85.0]),
            ExerciseData::from(['id' => 4, 'name' => 'Deadlift (Narrow)', 'modifier' => 105.0]),
            ExerciseData::from(['id' => 5, 'name' => 'Row', 'modifier' => 100.0]),
        ];

        $this->initializeStrategyConfig();
        $this->initializeRulesConfig();
        $this->updateConfig();
        $this->manager = ExerciseBlockManager::example($this->config);
    }

    protected function initializeStrategyConfig(): void
    {
        $strategyClass = ExerciseBlockManager::strategies()[$this->selectedStrategy] ?? null;
        if ($strategyClass) {
            $this->strategyConfig = $strategyClass::getDefaultConfig();
        }
    }

    protected function initializeRulesConfig(): void
    {
        $this->initialRulesConfig = AthleteExerciseConfig::getDefaultRulesConfig(
            AthleteExerciseConfig::initialRuleClasses()
        );
        $this->actionRulesConfig = AthleteExerciseConfig::getDefaultRulesConfig(
            AthleteExerciseConfig::actionRuleClasses()
        );

        foreach (AthleteExerciseConfig::initialRuleClasses() as $ruleClass) {
            $this->initialRulesEnabled[$ruleClass::getType()] = true;
        }
        foreach (AthleteExerciseConfig::actionRuleClasses() as $ruleClass) {
            $this->actionRulesEnabled[$ruleClass::getType()] = true;
        }
    }

    public function updatedSelectedAthleteId(): void
    {
        $this->updateConfig();
    }

    public function updatedSelectedExerciseId(): void
    {
        $this->updateConfig();
    }

    public function updatedTargetGoal(): void
    {
        $this->updateConfig();
    }

    public function updatedSelectedStrategy(): void
    {
        $this->initializeStrategyConfig();
        $this->updateConfig();
    }

    public function getStrategies(): array
    {
        return ExerciseBlockManager::strategies();
    }

    public function getStrategyFormFields(): array
    {
        $strategyClass = ExerciseBlockManager::strategies()[$this->selectedStrategy] ?? null;
        if (! $strategyClass) {
            return [];
        }

        return $strategyClass::getConfigureForm();
    }

    public function getInitialRules(): array
    {
        return AthleteExerciseConfig::getRulesInfo(
            AthleteExerciseConfig::initialRuleClasses()
        );
    }

    public function getActionRules(): array
    {
        return AthleteExerciseConfig::getRulesInfo(
            AthleteExerciseConfig::actionRuleClasses()
        );
    }

    public function openAdvancedModal(): void
    {
        $this->showAdvancedModal = true;
    }

    public function closeAdvancedModal(): void
    {
        $this->showAdvancedModal = false;
    }

    public function saveAdvancedConfig(): void
    {
        $rules = [];

        $strategyClass = ExerciseBlockManager::strategies()[$this->selectedStrategy] ?? null;
        if ($strategyClass) {
            $formFields = $strategyClass::getConfigureForm();
            foreach ($formFields as $actionType => $fieldset) {
                $fieldsetRules = FluxFieldset::buildValidationRules([$fieldset], "strategyConfig.{$actionType}.");
                $rules = array_merge($rules, $fieldsetRules);
            }
        }

        foreach ($this->getInitialRules() as $ruleType => $ruleInfo) {
            if (! ($this->initialRulesEnabled[$ruleType] ?? false) || $ruleInfo['fieldset'] === null) {
                continue;
            }
            $fieldsetRules = FluxFieldset::buildValidationRules([$ruleInfo['fieldset']], "initialRulesConfig.{$ruleType}.");
            $rules = array_merge($rules, $fieldsetRules);
        }

        foreach ($this->getActionRules() as $ruleType => $ruleInfo) {
            if (! ($this->actionRulesEnabled[$ruleType] ?? false) || $ruleInfo['fieldset'] === null) {
                continue;
            }
            $fieldsetRules = FluxFieldset::buildValidationRules([$ruleInfo['fieldset']], "actionRulesConfig.{$ruleType}.");
            $rules = array_merge($rules, $fieldsetRules);
        }

        if (! empty($rules)) {
            $this->validate($rules);
        }

        $this->updateConfig();
        $this->showAdvancedModal = false;
    }

    public function updateExerciseModifier(int $exerciseId, float $value): void
    {
        foreach ($this->exercises as $index => $exercise) {
            if ($exercise->id === $exerciseId) {
                $this->exercises[$index] = new ExerciseData(
                    id: $exercise->id,
                    name: $exercise->name,
                    modifier: $value,
                );
                break;
            }
        }
        $this->updateConfig();
    }

    public function updateAthleteTestReps(int $athleteId, int $testIndex, int $value): void
    {
        foreach ($this->athletes as $index => $athlete) {
            if ($athlete->id === $athleteId) {
                $tests = $athlete->tests;
                if (isset($tests[$testIndex])) {
                    $tests[$testIndex] = new AthleteTestData(
                        exerciseId: $tests[$testIndex]->exerciseId,
                        reps: $value,
                        weight: $tests[$testIndex]->weight,
                    );
                }
                $this->athletes[$index] = new AthleteData(
                    id: $athlete->id,
                    name: $athlete->name,
                    tests: $tests,
                );
                break;
            }
        }
        $this->updateConfig();
    }

    public function updateAthleteTestWeight(int $athleteId, int $testIndex, float $value): void
    {
        foreach ($this->athletes as $index => $athlete) {
            if ($athlete->id === $athleteId) {
                $tests = $athlete->tests;
                if (isset($tests[$testIndex])) {
                    $tests[$testIndex] = new AthleteTestData(
                        exerciseId: $tests[$testIndex]->exerciseId,
                        reps: $tests[$testIndex]->reps,
                        weight: $value,
                    );
                }
                $this->athletes[$index] = new AthleteData(
                    id: $athlete->id,
                    name: $athlete->name,
                    tests: $tests,
                );
                break;
            }
        }
        $this->updateConfig();
    }

    #[Computed]
    public function athlete(): AthleteData
    {
        return collect($this->athletes)->first(fn ($a) => $a->id === $this->selectedAthleteId) ?? $this->athletes[0];
    }

    #[Computed]
    public function exercise(): ExerciseData
    {
        return collect($this->exercises)->first(fn ($e) => $e->id === $this->selectedExerciseId) ?? $this->exercises[0];
    }

    private function updateConfig(): void
    {
        $this->config = AthleteExerciseConfig::fromAthleteExerciseAndTarget(
            athlete: $this->athlete,
            exercise: $this->exercise,
            target: $this->targetGoal,
            strategy: $this->selectedStrategy,
            strategyConfig: $this->strategyConfig,
            initialRulesConfig: $this->initialRulesConfig,
            actionRulesConfig: $this->actionRulesConfig,
            initialRulesEnabled: $this->initialRulesEnabled,
            actionRulesEnabled: $this->actionRulesEnabled,
        );
        $this->manager = ExerciseBlockManager::example($this->config);
    }

    public function render()
    {
        return view('livewire.calculator');
    }
}
