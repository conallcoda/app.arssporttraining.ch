<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSession;
use App\Models\Training\ExercisePlan\ExerciseSet;
use App\Models\Training\ExercisePlan\ExerciseWeek;

class SetWeekOneRepMaxProgressionLinear extends BlockAction
{
    public static function helpText(): string
    {
        return 'Distributes 1RM values evenly across sets within each week, creating a linear progression from the week\'s start to its target 1RM.';
    }

    public function apply(ExerciseBlock $block): BlockResult
    {
        $startingOneRepMax = $block->config->startingOneRepMax;
        $previousWeekTarget = null;

        $newWeeks = [];

        foreach ($block->weeks as $weekIndex => $week) {
            $weekStart = $weekIndex === 0
                ? $startingOneRepMax
                : $previousWeekTarget;

            $weekTarget = $week->lastSession()->lastSet()->oneRepMax;

            $newWeeks[] = $this->applyToWeek($week, $weekStart, $weekTarget);

            $previousWeekTarget = $weekTarget;
        }

        return $this->result($block, $block->withWeeks($newWeeks));
    }

    protected function applyToWeek(ExerciseWeek $week, float $weekStart, ?float $weekTarget): ExerciseWeek
    {
        $lastSessionIndex = $week->lastSessionIndex();
        $lastSession = $week->lastSession();
        $setCount = count($lastSession->sets);

        $stepSize = $setCount > 1 && $weekTarget !== null
            ? ($weekTarget - $weekStart) / ($setCount - 1)
            : 0;

        return $week->mapSessions(
            fn (ExerciseSession $session, int $sessionIndex) => $sessionIndex === $lastSessionIndex
                ? $session->mapSets(
                    fn (ExerciseSet $set, int $setIndex) => new ExerciseSet(
                        reps: $set->reps,
                        weight: $set->weight,
                        oneRepMax: $weekStart + ($stepSize * $setIndex),
                    )
                )
                : $session
        );
    }
}
