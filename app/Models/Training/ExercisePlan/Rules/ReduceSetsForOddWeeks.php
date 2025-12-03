<?php

namespace App\Models\Training\ExercisePlan\Rules;

use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSession;
use App\Models\Training\ExercisePlan\ExerciseSet;
use App\Models\Training\ExercisePlan\ExerciseWeek;

class ReduceSetsForOddWeeks extends BlockRule
{
    public function __construct(
        protected int $setsToReduceBy = 1,
    ) {}

    public function apply(ExerciseBlock $block): ExerciseBlock
    {
        $newWeeks = [];

        foreach ($block->weeks as $weekIndex => $week) {
            $weekNumber = $weekIndex + 1;

            if ($weekNumber % 2 === 0) {
                $newWeeks[] = $week;

                continue;
            }

            $newSessions = [];
            foreach ($week->sessions as $session) {
                $sets = $session->sets;
                $setsToKeep = max(0, count($sets) - $this->setsToReduceBy);

                $newSets = [];
                for ($i = 0; $i < $setsToKeep; $i++) {
                    $set = $sets[$i];
                    $newSets[] = new ExerciseSet(
                        reps: $set->reps,
                        weight: $set->weight,
                        oneRepMax: $set->oneRepMax,
                    );
                }

                $newSessions[] = new ExerciseSession(sets: $newSets);
            }

            $newWeeks[] = new ExerciseWeek(sessions: $newSessions);
        }

        return new ExerciseBlock(
            config: $block->config,
            weeks: $newWeeks,
        );
    }
}
