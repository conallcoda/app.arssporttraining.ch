<?php

namespace App\Models\Training\ExercisePlan\Rules;

use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSession;
use App\Models\Training\ExercisePlan\ExerciseSet;
use App\Models\Training\ExercisePlan\ExerciseWeek;

class DuplicateSessionsPerWeek extends BlockRule
{
    public function apply(ExerciseBlock $block): ExerciseBlock
    {
        $newWeeks = [];

        foreach ($block->weeks as $week) {
            $sessions = $week->sessions;
            $lastSession = $sessions[count($sessions) - 1];

            $newSessions = [];
            foreach ($sessions as $session) {
                $newSets = [];
                foreach ($lastSession->sets as $set) {
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
