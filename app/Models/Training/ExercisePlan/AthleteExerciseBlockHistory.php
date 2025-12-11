<?php

namespace App\Models\Training\ExercisePlan;

use App\Data\AbstractData;
use App\Models\Training\ExercisePlan\Actions\BlockResult;
use App\Models\Training\ExercisePlan\Strategies\AbstractStrategy;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class AthleteExerciseBlockHistory extends AbstractData
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

        $results = BlockProgressionEngine::generateFullProgression($config);

        return new self(
            config: $config,
            results: $results
        );
    }

    public static function getStrategy(string $name): AbstractStrategy
    {
        return BlockProgressionEngine::getStrategy($name);
    }

    public static function strategies(): array
    {
        return BlockProgressionEngine::strategies();
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
