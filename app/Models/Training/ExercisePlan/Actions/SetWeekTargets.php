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
        $weeks = $block->weeks;
        $lastWeekIndex = count($weeks) - 1;

        $lastWeek = $weeks[$lastWeekIndex];
        $lastSession = $lastWeek->sessions[count($lastWeek->sessions) - 1];
        $lastSet = $lastSession->sets[count($lastSession->sets) - 1];
        $targetOneRepMax = $lastSet->oneRepMax ?? $block->config->targetOneRepMax;

        $newWeeks = [];

        $stepDownCount = $this->countStepDowns($lastWeekIndex);
        for ($i = 0; $i < $stepDownCount; $i++) {
            $targetOneRepMax = FixedWeightStep::decrement($targetOneRepMax);
        }

        $currentOneRepMax = $targetOneRepMax;

        foreach ($weeks as $weekIndex => $week) {
            if ($weekIndex === $lastWeekIndex) {
                $newWeeks[] = $week;

                continue;
            }

            $sessions = $week->sessions;
            $lastSessionIndex = count($sessions) - 1;
            $lastSession = $sessions[$lastSessionIndex];
            $sets = $lastSession->sets;
            $lastSetIndex = count($sets) - 1;

            $newSets = [];
            foreach ($sets as $setIndex => $set) {
                if ($setIndex === $lastSetIndex) {
                    $newSets[] = new ExerciseSet(
                        reps: $set->reps,
                        weight: $set->weight,
                        oneRepMax: $currentOneRepMax,
                    );
                } else {
                    $newSets[] = $set;
                }
            }

            $newSessions = [];
            foreach ($sessions as $sessionIndex => $session) {
                if ($sessionIndex === $lastSessionIndex) {
                    $newSessions[] = new ExerciseSession(sets: $newSets);
                } else {
                    $newSessions[] = $session;
                }
            }

            $newWeeks[] = new ExerciseWeek(sessions: $newSessions);

            $weeksFromTarget = $lastWeekIndex - $weekIndex;
            $positionInGroup = ($weeksFromTarget - 1) % $this->stepDownInterval;
            if ($positionInGroup === 0) {
                $currentOneRepMax = FixedWeightStep::increment($currentOneRepMax);
            }
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

    protected function countStepDowns(int $lastWeekIndex): int
    {
        $count = 0;

        for ($weekIndex = 0; $weekIndex < $lastWeekIndex; $weekIndex++) {
            $weeksFromTarget = $lastWeekIndex - $weekIndex;
            $positionInGroup = ($weeksFromTarget - 1) % $this->stepDownInterval;
            if ($positionInGroup === 0) {
                $count++;
            }
        }

        return $count;
    }
}
