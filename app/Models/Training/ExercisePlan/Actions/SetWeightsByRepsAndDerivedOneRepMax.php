<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSession;
use App\Models\Training\ExercisePlan\ExerciseSet;
use App\Models\Training\ExercisePlan\ExerciseWeek;
use App\Models\Training\ExercisePlan\OneRepMaxConverter;

class SetWeightsByRepsAndDerivedOneRepMax extends BlockAction
{
    public function getType(): string
    {
        return 'set_weights_by_reps_and_derived_one_rep_max';
    }

    public function apply(ExerciseBlock $block): BlockResult
    {
        $newWeeks = [];

        foreach ($block->weeks as $week) {
            $newSessions = [];

            foreach ($week->sessions as $session) {
                $newSets = [];

                foreach ($session->sets as $set) {
                    $weight = null;

                    if ($set->oneRepMax !== null && $set->reps !== null) {
                        $weight = OneRepMaxConverter::getWeight($set->reps, $set->oneRepMax);
                    }

                    $newSets[] = new ExerciseSet(
                        reps: $set->reps,
                        weight: $weight,
                        oneRepMax: $set->oneRepMax,
                    );
                }

                $newSessions[] = new ExerciseSession(sets: $newSets);
            }

            $newWeeks[] = new ExerciseWeek(sessions: $newSessions);
        }

        $newBlock = new ExerciseBlock(
            config: $block->config,
            weeks: $newWeeks,
        );

        return new BlockResult(
            action: $this,
            previous: $block,
            current: $newBlock,
        );
    }
}
