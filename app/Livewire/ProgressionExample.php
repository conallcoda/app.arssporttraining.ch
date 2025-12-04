<?php

namespace App\Livewire;

use App\Data\Form\FluxFieldset;
use App\Models\Training\ExercisePlan\AthleteData;
use App\Models\Training\ExercisePlan\AthleteExerciseConfig;
use App\Models\Training\ExercisePlan\AthleteTestData;
use App\Models\Training\ExercisePlan\ExerciseBlockManager;
use App\Models\Training\ExercisePlan\ExerciseData;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Attributes\Title;

#[Layout('components.layouts.app')]
#[Title('Training Plan Calculator')]
class ProgressionExample extends Component
{
    public array $athletes = [];

    public AthleteData $athlete;

    public array $exercises = [];

    public ExerciseData $exercise;

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

        $this->athlete = $this->athletes[0];
        $this->exercises = [
            ExerciseData::back_squat(),
            ExerciseData::front_squat(),
        ];

        $this->exercise = $this->exercises[1];
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
        $this->athlete = collect($this->athletes)->first(fn($a) => $a->id === $this->selectedAthleteId) ?? $this->athletes[0];
        $this->updateConfig();
    }

    public function updatedSelectedExerciseId(): void
    {
        $this->exercise = collect($this->exercises)->first(fn($e) => $e->id === $this->selectedExerciseId) ?? $this->exercises[0];
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
        $this->exercise = collect($this->exercises)->first(fn($e) => $e->id === $this->selectedExerciseId) ?? $this->exercises[0];
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
        $this->athlete = collect($this->athletes)->first(fn($a) => $a->id === $this->selectedAthleteId) ?? $this->athletes[0];
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
        $this->athlete = collect($this->athletes)->first(fn($a) => $a->id === $this->selectedAthleteId) ?? $this->athletes[0];
        $this->updateConfig();
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

    private array $docToc = [];

    private string $docHtml = '';

    protected function parseDocumentation(): void
    {
        if ($this->docHtml !== '') {
            return;
        }

        $filePath = base_path('docs/training-plans.md');

        if (! file_exists($filePath)) {
            return;
        }

        $markdown = file_get_contents($filePath);

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $html = $converter->convert($markdown)->getContent();

        $toc = [];
        $counters = [0, 0, 0, 0, 0, 0];

        $html = preg_replace_callback(
            '/<h([1-6])>(.+?)<\/h[1-6]>/i',
            function ($matches) use (&$counters, &$toc) {
                $level = (int) $matches[1];
                $text = strip_tags($matches[2]);
                $slug = \Illuminate\Support\Str::slug($text);

                $numberHtml = '';
                $number = '';
                if ($level >= 2) {
                    $counters[$level - 1]++;
                    for ($i = $level; $i < 6; $i++) {
                        $counters[$i] = 0;
                    }

                    $numberParts = [];
                    for ($i = 1; $i < $level; $i++) {
                        $numberParts[] = $counters[$i];
                    }
                    $number = implode('.', $numberParts);
                    $numberHtml = '<span class="text-zinc-400 dark:text-zinc-500 mr-2">' . $number . '</span>';

                    if ($level <= 3) {
                        $toc[] = [
                            'level' => $level,
                            'title' => $text,
                            'slug' => $slug,
                            'number' => $number,
                        ];
                    }
                }

                return '<h' . $level . ' id="' . $slug . '" class="scroll-mt-16">' . $numberHtml . $matches[2] . '</h' . $level . '>';
            },
            $html
        );

        $this->docToc = $toc;
        $this->docHtml = $html;
    }

    #[Computed]
    public function documentationToc(): array
    {
        $this->parseDocumentation();

        return $this->docToc;
    }

    #[Computed]
    public function documentationHtml(): string
    {
        $this->parseDocumentation();

        return $this->docHtml;
    }

    public function render()
    {
        return view('livewire.progression-example');
    }
}
