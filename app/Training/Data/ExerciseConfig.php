<?php

namespace App\Training\Data;

use App\Cms\Data\AbstractData;

class ExerciseConfig extends AbstractData
{
    public function __construct(
        public float $starting1RM,
        public float $target1RM,
        public float $target,
        public int $startingReps,
        public int $sets,
        public int $sessionsPerWeek = 2,
        public bool $deloadEnabled = true,
        public int $deloadSetsReduction = 1,
    ) {}

    public static function fromMeasuredData(
        float $measuredWeight,
        int $measuredReps,
        float $oneRepMaxModifier,
        float $targetPercentage,
        int $startingReps,
        int $sets,
        int $sessionsPerWeek = 2,
        bool $deloadEnabled = true,
        int $deloadSetsReduction = 1,
    ): self {
        $repPercentage = \App\Training\Reference\RepPercentageTable::getPercentage($measuredReps);
        $baseline1RM = $measuredWeight / $repPercentage;
        $exercise1RM = $baseline1RM * ($oneRepMaxModifier / 100);
        $target1RM = $exercise1RM * (1 + $targetPercentage / 100);

        return new self(
            starting1RM: round($exercise1RM, 1),
            target1RM: round($target1RM, 1),
            target: $targetPercentage,
            startingReps: $startingReps,
            sets: $sets,
            sessionsPerWeek: $sessionsPerWeek,
            deloadEnabled: $deloadEnabled,
            deloadSetsReduction: $deloadSetsReduction,
        );
    }
}
