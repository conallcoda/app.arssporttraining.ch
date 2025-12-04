<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSet;

class SetOneRepMaxBlockTarget extends BlockAction
{
    public static function helpText(): string
    {
        return 'Sets the target 1RM value on the last set of the final session of the last week. This defines the strength goal the athlete is working towards.';
    }

    public function apply(ExerciseBlock $block): BlockResult
    {
        $lastWeekIndex = $block->lastWeekIndex();
        $lastSessionIndex = $block->lastWeek()->lastSessionIndex();
        $lastSetIndex = $block->lastWeek()->lastSession()->lastSetIndex();
        $targetOneRepMax = $block->config->targetOneRepMax;

        $newBlock = $block->mapWeeks(fn ($week, int $weekIndex) => $weekIndex === $lastWeekIndex
                ? $week->mapSessions(fn ($session, int $sessionIndex) => $sessionIndex === $lastSessionIndex
                        ? $session->mapSets(fn (ExerciseSet $set, int $setIndex) => $setIndex === $lastSetIndex
                                ? new ExerciseSet(
                                    reps: $set->reps,
                                    weight: $set->weight,
                                    oneRepMax: $targetOneRepMax,
                                )
                                : $set
                        )
                        : $session
                )
                : $week
        );

        return $this->result($block, $newBlock);
    }
}
