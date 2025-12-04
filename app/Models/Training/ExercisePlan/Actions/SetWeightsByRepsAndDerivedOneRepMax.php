<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSet;
use App\Models\Training\ExercisePlan\OneRepMaxConverter;

class SetWeightsByRepsAndDerivedOneRepMax extends BlockAction
{
    public function apply(ExerciseBlock $block): BlockResult
    {
        $newBlock = $block->mapWeeks(fn ($week) => $week->mapSessions(fn ($session) => $session->mapSets(fn (ExerciseSet $set) => new ExerciseSet(
            reps: $set->reps,
            weight: $set->oneRepMax !== null && $set->reps !== null
                ? OneRepMaxConverter::getWeight($set->reps, $set->oneRepMax)
                : null,
            oneRepMax: $set->oneRepMax,
        )
        )
        )
        );

        return $this->result($block, $newBlock);
    }
}
