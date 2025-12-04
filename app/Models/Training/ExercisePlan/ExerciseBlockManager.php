<?php

namespace App\Models\Training\ExercisePlan;

use App\Data\AbstractData;
use App\Models\Training\ExercisePlan\Actions\BlockResult;
use App\Models\Training\ExercisePlan\Strategies\AbstractStrategy;
use App\Models\Training\ExercisePlan\Strategies\FixedDecrement;
use App\Models\Training\ExercisePlan\Strategies\LinearProgression;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class ExerciseBlockManager extends AbstractData
{
    public function __construct(
        public AthleteExerciseConfig $config,
        #[DataCollectionOf(BlockResult::class)]
        public array $results = [],
    ) {}

    public static function example(?AthleteExerciseConfig $config = null): self
    {
        $config = $config ?? AthleteExerciseConfig::fromAthleteExerciseAndTarget(
            athlete: AthleteData::example(),
            exercise: ExerciseData::front_squat(),
            target: 10,
        );

        $current = ExerciseBlock::example($config);

        $actions = static::getStrategy($config->strategy)::actions([]);
        foreach ($actions as $index => $action) {
            $result = $action->apply($current);

            if ($index === 0) {
                $result->current = static::applyRules($result->current, $config->initialRules);
            }

            $result->current = static::applyRules($result->current, $config->actionRules);
            $current = $result->current;

            $results[] = $result;
        }

        return new self(
            config: $config,
            results: $results ?? []
        );
    }

    public static function getStrategy($name): AbstractStrategy
    {
        $strategies = static::strategies();

        if (! array_key_exists($name, $strategies)) {
            return new $strategies['fixed_decrement'];
        }

        return new $strategies[$name];
    }

    public static function strategies()
    {
        return [
            'fixed_decrement' => FixedDecrement::class,
            'linear_progression' => LinearProgression::class,
        ];
    }

    protected static function applyRules(ExerciseBlock $block, array $rules): ExerciseBlock
    {
        foreach ($rules as $rule) {
            $block = $rule->apply($block);
        }

        return $block;
    }

    public function athlete(): AthleteData
    {
        return $this->config->athlete;
    }

    public function exercise(): ExerciseData
    {
        return $this->config->exercise;
    }

    public function current(): ExerciseBlock
    {
        return end($this->results)->current;
    }
}
