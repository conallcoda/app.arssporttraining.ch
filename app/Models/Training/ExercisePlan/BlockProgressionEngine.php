<?php

namespace App\Models\Training\ExercisePlan;

use App\Models\Training\ExercisePlan\Actions\BlockAction;
use App\Models\Training\ExercisePlan\Actions\BlockResult;
use App\Models\Training\ExercisePlan\Strategies\AbstractStrategy;
use App\Models\Training\ExercisePlan\Strategies\FixedDecrement;
use App\Models\Training\ExercisePlan\Strategies\LinearProgression;

class BlockProgressionEngine
{
    public static function strategies(): array
    {
        return [
            'fixed_decrement' => FixedDecrement::class,
            'linear_progression' => LinearProgression::class,
        ];
    }

    public static function getStrategy(string $name): AbstractStrategy
    {
        $strategies = static::strategies();

        if (! array_key_exists($name, $strategies)) {
            return new $strategies['fixed_decrement'];
        }

        return new $strategies[$name];
    }

    public static function getStrategyActions(AthleteExerciseConfig $config): array
    {
        $strategyClass = static::strategies()[$config->strategy] ?? FixedDecrement::class;
        $defaultConfig = $strategyClass::getDefaultConfig();
        $strategyConfig = array_replace_recursive($defaultConfig, $config->strategyConfig);

        return static::getStrategy($config->strategy)::configure($strategyConfig);
    }

    public static function applyAction(
        BlockAction $action,
        ExerciseBlock $block,
        AthleteExerciseConfig $config,
        bool $applyInitialRules = false
    ): BlockResult {
        $result = $action->apply($block);

        if ($applyInitialRules) {
            $result->current = static::applyRules($result->current, $config->initialRules);
        }

        $result->current = static::applyRules($result->current, $config->actionRules);

        return $result;
    }

    public static function applyRules(ExerciseBlock $block, array $rules): ExerciseBlock
    {
        foreach ($rules as $rule) {
            $block = $rule->apply($block);
        }

        return $block;
    }

    public static function generateFullProgression(AthleteExerciseConfig $config): array
    {
        $current = ExerciseBlock::example($config);
        $actions = static::getStrategyActions($config);
        $results = [];

        foreach ($actions as $index => $action) {
            $result = static::applyAction(
                action: $action,
                block: $current,
                config: $config,
                applyInitialRules: $index === 0
            );

            $current = $result->current;
            $results[] = $result;
        }

        return $results;
    }
}
