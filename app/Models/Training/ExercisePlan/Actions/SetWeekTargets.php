<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSession;
use App\Models\Training\ExercisePlan\ExerciseSet;
use App\Models\Training\ExercisePlan\ExerciseWeek;
use App\Models\Training\ExercisePlan\FixedWeightStep;

class SetWeekTargets extends BlockAction
{
    public function __construct(
        protected int $stepDownInterval = 2,
    ) {}

    public function getType(): string
    {
        return 'set_week_targets';
    }

    public function apply(ExerciseBlock $block): BlockResult
    {
        $lastWeekIndex = $block->lastWeekIndex();
        $lastSet = $block->lastWeek()->lastSession()->lastSet();
        $targetOneRepMax = $lastSet->oneRepMax ?? $block->config->targetOneRepMax;

        $stepDownCount = $this->countStepDowns($lastWeekIndex);
        for ($i = 0; $i < $stepDownCount; $i++) {
            $targetOneRepMax = FixedWeightStep::decrement($targetOneRepMax);
        }

        $currentOneRepMax = $targetOneRepMax;
        $newWeeks = [];

        foreach ($block->weeks as $weekIndex => $week) {
            if ($weekIndex === $lastWeekIndex) {
                $newWeeks[] = $week;

                continue;
            }

            $newWeeks[] = $this->applyToWeek($week, $currentOneRepMax);

            $weeksFromTarget = $lastWeekIndex - $weekIndex;
            if (($weeksFromTarget - 1) % $this->stepDownInterval === 0) {
                $currentOneRepMax = FixedWeightStep::increment($currentOneRepMax);
            }
        }

        return $this->result($block, $block->withWeeks($newWeeks));
    }

    protected function applyToWeek(ExerciseWeek $week, float $currentOneRepMax): ExerciseWeek
    {
        $lastSessionIndex = $week->lastSessionIndex();
        $lastSetIndex = $week->lastSession()->lastSetIndex();

        return $week->mapSessions(fn (ExerciseSession $session, int $sessionIndex) => $sessionIndex === $lastSessionIndex
                ? $session->mapSets(fn (ExerciseSet $set, int $setIndex) => $setIndex === $lastSetIndex
                        ? new ExerciseSet(
                            reps: $set->reps,
                            weight: $set->weight,
                            oneRepMax: $currentOneRepMax,
                        )
                        : $set
                )
                : $session
        );
    }

    protected function countStepDowns(int $lastWeekIndex): int
    {
        $count = 0;

        for ($weekIndex = 0; $weekIndex < $lastWeekIndex; $weekIndex++) {
            $weeksFromTarget = $lastWeekIndex - $weekIndex;
            if (($weeksFromTarget - 1) % $this->stepDownInterval === 0) {
                $count++;
            }
        }

        return $count;
    }
}
