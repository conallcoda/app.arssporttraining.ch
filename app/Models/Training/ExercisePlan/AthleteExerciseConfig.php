<?php

namespace App\Models\Training\ExercisePlan;

use App\Data\AbstractData;
use App\Data\Form\FluxFieldset;

class AthleteExerciseConfig extends AbstractData
{
    public function __construct(
        public AthleteData $athlete,
        public ExerciseData $exercise,
        public float $startingOneRepMax,
        public float $targetOneRepMax,
        public float $target,
        public array $initialRules = [],
        public array $actionRules = [],
        public string $strategy = 'fixed_decrement',
        public array $strategyConfig = [],
        public array $initialRulesConfig = [],
        public array $actionRulesConfig = [],
    ) {}

    public static function initialRuleClasses(): array
    {
        return [
            Rules\ReduceSetsForOddWeeks::class,
        ];
    }

    public static function actionRuleClasses(): array
    {
        return [
            Rules\RoundWeightsToNearestStep::class,
            Rules\DuplicateSessionsPerWeek::class,
        ];
    }

    public static function getDefaultRulesConfig(array $ruleClasses): array
    {
        $config = [];

        foreach ($ruleClasses as $ruleClass) {
            $ruleFields = $ruleClass::getFields();
            if (empty($ruleFields)) {
                continue;
            }

            $instance = new $ruleClass;
            $reflection = new \ReflectionClass($ruleClass);
            $constructor = $reflection->getConstructor();

            if (! $constructor) {
                continue;
            }

            $ruleConfig = [];
            foreach ($constructor->getParameters() as $param) {
                $name = $param->getName();
                if ($param->isDefaultValueAvailable()) {
                    $ruleConfig[$name] = $param->getDefaultValue();
                } elseif ($reflection->hasProperty($name)) {
                    $prop = $reflection->getProperty($name);
                    $prop->setAccessible(true);
                    $ruleConfig[$name] = $prop->getValue($instance);
                }
            }

            if (! empty($ruleConfig)) {
                $config[$ruleClass::getType()] = $ruleConfig;
            }
        }

        return $config;
    }

    public static function getRulesFormFields(array $ruleClasses): array
    {
        $fields = [];

        foreach ($ruleClasses as $ruleClass) {
            $ruleFields = $ruleClass::getFields();

            if (count($ruleFields) >= 1) {
                $fields[$ruleClass::getType()] = FluxFieldset::make($ruleClass::getTitle())
                    ->schema($ruleFields);
            }
        }

        return $fields;
    }

    public static function getRulesInfo(array $ruleClasses): array
    {
        $info = [];

        foreach ($ruleClasses as $ruleClass) {
            $ruleFields = $ruleClass::getFields();
            $type = $ruleClass::getType();

            $info[$type] = [
                'title' => $ruleClass::getTitle(),
                'type' => $type,
                'class' => $ruleClass,
                'hasFields' => count($ruleFields) > 0,
                'fieldset' => count($ruleFields) > 0
                    ? FluxFieldset::make($ruleClass::getTitle())->schema($ruleFields)
                    : null,
            ];
        }

        return $info;
    }

    public static function configureRules(array $ruleClasses, array $config, array $enabled = []): array
    {
        $rules = [];

        foreach ($ruleClasses as $ruleClass) {
            $type = $ruleClass::getType();

            if (! empty($enabled) && ! ($enabled[$type] ?? false)) {
                continue;
            }

            $rules[] = $ruleClass::from($config[$type] ?? []);
        }

        return $rules;
    }

    public static function example(): self
    {
        return self::fromAthleteExerciseAndTarget(
            athlete: AthleteData::example(),
            exercise: ExerciseData::front_squat(),
            target: 10,
        );
    }

    public static function fromAthleteExerciseAndTarget(
        AthleteData $athlete,
        ExerciseData $exercise,
        float $target,
        string $strategy = 'fixed_decrement',
        array $strategyConfig = [],
        array $initialRulesConfig = [],
        array $actionRulesConfig = [],
        array $initialRulesEnabled = [],
        array $actionRulesEnabled = [],
    ): self {
        $startingOneRepMax = $athlete->getOneRepMaxForExercise($exercise);
        $increase = $startingOneRepMax * ($target / 100);

        $initialRulesConfig = array_replace_recursive(
            self::getDefaultRulesConfig(self::initialRuleClasses()),
            $initialRulesConfig
        );

        $actionRulesConfig = array_replace_recursive(
            self::getDefaultRulesConfig(self::actionRuleClasses()),
            $actionRulesConfig
        );

        return new self(
            athlete: $athlete,
            exercise: $exercise,
            startingOneRepMax: $startingOneRepMax,
            targetOneRepMax: $startingOneRepMax + $increase,
            target: $target,
            initialRules: self::configureRules(self::initialRuleClasses(), $initialRulesConfig, $initialRulesEnabled),
            actionRules: self::configureRules(self::actionRuleClasses(), $actionRulesConfig, $actionRulesEnabled),
            strategy: $strategy,
            strategyConfig: $strategyConfig,
            initialRulesConfig: $initialRulesConfig,
            actionRulesConfig: $actionRulesConfig,
        );
    }
}
