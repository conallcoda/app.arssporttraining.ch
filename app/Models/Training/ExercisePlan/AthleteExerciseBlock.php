<?php

namespace App\Models\Training\ExercisePlan;

use App\Data\AbstractData;

class AthleteExerciseBlock extends AbstractData
{
    public function __construct(
        public AthleteExerciseConfig $config,
        public ExerciseBlock $block,
    ) {}

    public static function fromConfig(AthleteExerciseConfig $config): self
    {
        $results = BlockProgressionEngine::generateFullProgression($config);
        $finalBlock = end($results)->current;

        return new self(
            config: $config,
            block: $finalBlock
        );
    }

    public static function example(?AthleteExerciseConfig $config = null): self
    {
        $config = $config ?? AthleteExerciseConfig::fromAthleteExerciseAndTarget(
            athlete: AthleteData::example(),
            exercise: ExerciseData::front_squat(),
            target: 10,
        );

        return static::fromConfig($config);
    }

    public function athlete(): AthleteData
    {
        return $this->config->athlete;
    }

    public function exercise(): ExerciseData
    {
        return $this->config->exercise;
    }
}
