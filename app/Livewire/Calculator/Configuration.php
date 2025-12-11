<?php

namespace App\Livewire\Calculator;

use App\Data\Form\FluxFieldset;
use App\Models\Training\ExercisePlan\AthleteExerciseConfig;
use App\Models\Training\ExercisePlan\BlockProgressionEngine;
use Livewire\Attributes\Modelable;
use Livewire\Component;

class Configuration extends Component
{
    #[Modelable]
    public float $targetGoal = 10;

    #[Modelable]
    public string $selectedStrategy = 'fixed_decrement';

    public array $strategyConfig = [];

    public array $initialRulesConfig = [];

    public array $actionRulesConfig = [];

    public array $initialRulesEnabled = [];

    public array $actionRulesEnabled = [];

    public bool $showAdvancedModal = false;

    public function mount(): void
    {
        $this->initializeStrategyConfig();
        $this->initializeRulesConfig();
        $this->emitConfigData();
    }

    protected function initializeStrategyConfig(): void
    {
        $strategyClass = BlockProgressionEngine::strategies()[$this->selectedStrategy] ?? null;
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

    public function updatedTargetGoal(): void
    {
        $this->emitConfigData();
    }

    public function updatedSelectedStrategy(): void
    {
        $this->initializeStrategyConfig();
        $this->emitConfigData();
    }

    public function updatedStrategyConfig(): void
    {
        $this->emitConfigData();
    }

    public function getStrategies(): array
    {
        return BlockProgressionEngine::strategies();
    }

    public function getStrategyFormFields(): array
    {
        $strategyClass = BlockProgressionEngine::strategies()[$this->selectedStrategy] ?? null;
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

        $strategyClass = BlockProgressionEngine::strategies()[$this->selectedStrategy] ?? null;
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

        $this->emitConfigData();
        $this->showAdvancedModal = false;
    }

    protected function emitConfigData(): void
    {
        $this->dispatch('config-changed', configData: [
            'targetGoal' => $this->targetGoal,
            'selectedStrategy' => $this->selectedStrategy,
            'strategyConfig' => $this->strategyConfig,
            'initialRulesConfig' => $this->initialRulesConfig,
            'actionRulesConfig' => $this->actionRulesConfig,
            'initialRulesEnabled' => $this->initialRulesEnabled,
            'actionRulesEnabled' => $this->actionRulesEnabled,
        ]);
    }

    public function render()
    {
        return view('livewire.calculator.configuration');
    }
}
