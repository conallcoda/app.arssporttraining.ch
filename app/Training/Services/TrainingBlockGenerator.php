<?php

namespace App\Training\Services;

use App\Training\Data\ExerciseConfig;
use App\Training\Data\TrainingBlock;
use App\Training\Strategies\FixedDecrementStrategy;
use App\Training\Strategies\StrategyInterface;

class TrainingBlockGenerator
{
    public function __construct(
        protected ?StrategyInterface $strategy = null,
    ) {
        $this->strategy = $strategy ?? new FixedDecrementStrategy;
    }

    public function generate(
        float $measuredWeight,
        int $measuredReps,
        float $oneRepMaxModifier,
        float $targetPercentage,
        int $startingReps,
        int $sets,
        int $weeks,
        int $sessionsPerWeek = 2,
        bool $deloadEnabled = true,
        int $deloadSetsReduction = 1,
    ): TrainingBlock {
        $config = ExerciseConfig::fromMeasuredData(
            measuredWeight: $measuredWeight,
            measuredReps: $measuredReps,
            oneRepMaxModifier: $oneRepMaxModifier,
            targetPercentage: $targetPercentage,
            startingReps: $startingReps,
            sets: $sets,
            sessionsPerWeek: $sessionsPerWeek,
            deloadEnabled: $deloadEnabled,
            deloadSetsReduction: $deloadSetsReduction,
        );

        return $this->strategy->generate($config, $weeks);
    }

    public function generateFromConfig(ExerciseConfig $config, int $weeks): TrainingBlock
    {
        return $this->strategy->generate($config, $weeks);
    }
}
