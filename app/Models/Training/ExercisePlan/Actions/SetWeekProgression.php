<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSession;
use App\Models\Training\ExercisePlan\ExerciseSet;
use App\Models\Training\ExercisePlan\ExerciseWeek;
use App\Models\Training\ExercisePlan\FixedWeightStep;

class SetWeekProgression extends BlockAction
{
    public function __construct(
        protected int $stepDownInterval = 2,
    ) {}

    public function getType(): string
    {
        return 'set_week_progression';
    }

    public function apply(ExerciseBlock $block): BlockResult
    {
        $newWeeks = [];

        foreach ($block->weeks as $week) {
            $sessions = $week->sessions;
            $lastSessionIndex = count($sessions) - 1;
            $lastSession = $sessions[$lastSessionIndex];
            $sets = $lastSession->sets;
            $lastSetIndex = count($sets) - 1;

            $lastSet = $sets[$lastSetIndex];
            $setCount = count($sets);

            $totalGroups = (int) ceil($setCount / $this->stepDownInterval);

            $newSets = [];
            for ($setIndex = 0; $setIndex < $setCount; $setIndex++) {
                $set = $sets[$setIndex];
                $groupFromStart = intdiv($setIndex, $this->stepDownInterval);
                $groupFromEnd = $totalGroups - 1 - $groupFromStart;
                $oneRepMaxForSet = $lastSet->oneRepMax;

                for ($i = 0; $i < $groupFromEnd; $i++) {
                    $oneRepMaxForSet = FixedWeightStep::decrement($oneRepMaxForSet);
                }

                $newSets[] = new ExerciseSet(
                    reps: $set->reps,
                    weight: $set->weight,
                    oneRepMax: $oneRepMaxForSet,
                );
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
