<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSession;
use App\Models\Training\ExercisePlan\ExerciseSet;
use App\Models\Training\ExercisePlan\ExerciseWeek;

class SetBlockTarget extends BlockAction
{
    public function getType(): string
    {
        return 'set_block_target';
    }

    public function apply(ExerciseBlock $block): BlockResult
    {
        $weeks = $block->weeks;
        $lastWeekIndex = count($weeks) - 1;
        $lastWeek = $weeks[$lastWeekIndex];

        $sessions = $lastWeek->sessions;
        $lastSessionIndex = count($sessions) - 1;
        $lastSession = $sessions[$lastSessionIndex];

        $sets = $lastSession->sets;
        $lastSetIndex = count($sets) - 1;

        $targetOneRepMax = $block->config->targetOneRepMax;

        $newSets = [];
        foreach ($sets as $index => $set) {
            if ($index === $lastSetIndex) {
                $newSets[] = new ExerciseSet(
                    reps: $set->reps,
                    weight: $set->weight,
                    oneRepMax: $targetOneRepMax,
                );
            } else {
                $newSets[] = $set;
            }
        }

        $newSessions = [];
        foreach ($lastWeek->sessions as $index => $session) {
            if ($index === $lastSessionIndex) {
                $newSessions[] = new ExerciseSession(sets: $newSets);
            } else {
                $newSessions[] = $session;
            }
        }

        $newWeeks = [];
        foreach ($weeks as $index => $week) {
            if ($index === $lastWeekIndex) {
                $newWeeks[] = new ExerciseWeek(sessions: $newSessions);
            } else {
                $newWeeks[] = $week;
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
}
