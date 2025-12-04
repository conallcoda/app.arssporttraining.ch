<?php

namespace App\Models\Training\ExercisePlan\Rules;

use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSet;

class RoundWeightsToNearestStep extends BlockRule
{
    public function __construct(
        protected float $step = 0.5,
    ) {}

    public function apply(ExerciseBlock $block): ExerciseBlock
    {
        return $block->mapWeeks(
            fn ($week) => $week->mapSessions(
                fn ($session) => $session->mapSets(
                    fn (ExerciseSet $set) => new ExerciseSet(
                        reps: $set->reps,
                        weight: $set->weight !== null
                            ? round($set->weight / $this->step) * $this->step
                            : null,
                        oneRepMax: $set->oneRepMax,
                    )
                )
            )
        );
    }
}
