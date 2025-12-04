<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSet;

class SetOneRepMaxBlockStart extends BlockAction
{
    public static function helpText(): string
    {
        return 'Sets the starting 1RM value on the first set of the last session of week one. This establishes the athlete\'s baseline strength for the training block.';
    }

    public function apply(ExerciseBlock $block): BlockResult
    {
        $firstWeek = $block->weeks[0] ?? null;
        if (! $firstWeek) {
            return $this->result($block, $block);
        }

        $lastSessionIndex = $firstWeek->lastSessionIndex();
        $startingOneRepMax = $block->config->startingOneRepMax;

        $newBlock = $block->mapWeeks(fn ($week, int $weekIndex) => $weekIndex === 0
                ? $week->mapSessions(fn ($session, int $sessionIndex) => $sessionIndex === $lastSessionIndex
                        ? $session->mapSets(fn (ExerciseSet $set, int $setIndex) => $setIndex === 0
                                ? new ExerciseSet(
                                    reps: $set->reps,
                                    weight: $set->weight,
                                    oneRepMax: $startingOneRepMax,
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
