<?php

namespace App\Models\Training\ExercisePlan;

use App\Data\AbstractData;

class AthleteExerciseConfig extends AbstractData
{
    public function __construct(
        public AthleteData $athlete,
        public ExerciseData $exercise,
        public float $startingOneRepMax,
        public float $targetOneRepMax,
        public float $target,
        public int $startingReps = 14,
        public array $initialRules = [],
        public array $actionRules = [],
    ) {}

    public static function example(): self
    {
        return self::fromAthleteExerciseAndTarget(
            athlete: AthleteData::example(),
            exercise: ExerciseData::front_squat(),
            target: 10,
        );
    }

    public static function fromAthleteExerciseAndTarget(AthleteData $athlete, ExerciseData $exercise, float $target, int $startingReps = 14): self
    {
        $startingOneRepMax = $athlete->getOneRepMaxForExercise($exercise);
        $increase = $startingOneRepMax * ($target / 100);

        return new self(
            athlete: $athlete,
            exercise: $exercise,
            startingOneRepMax: $startingOneRepMax,
            targetOneRepMax: $startingOneRepMax + $increase,
            target: $target,
            startingReps: $startingReps,
            initialRules: [
                new Rules\ReduceSetsForOddWeeks(1),
            ],
            actionRules: [
                new Rules\DuplicateSessionsPerWeek,
            ],
        );
    }
}
