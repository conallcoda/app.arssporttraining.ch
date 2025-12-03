<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSession;
use App\Models\Training\ExercisePlan\ExerciseSet;
use App\Models\Training\ExercisePlan\ExerciseWeek;

class SetPairedReps extends BlockAction
{
    public function getType(): string
    {
        return 'set_paired_reps';
    }

    public function __construct(
        protected int $startingReps = 14,
        protected int $stepDownInterval = 2,
        protected int $repDecrement = 2,
        protected int $minimumReps = 1,
    ) {}

    public function apply(ExerciseBlock $block): BlockResult
    {
        $blockLength = count($block->weeks);
        $newWeeks = [];

        foreach ($block->weeks as $weekIndex => $week) {
            $anchorReps = $this->getAnchorRepsForWeek($weekIndex, $blockLength);
            $firstTierReps = $anchorReps + $this->repDecrement;

            $newSessions = [];
            foreach ($week->sessions as $session) {
                $totalSets = count($session->sets);

                $newSets = [];
                foreach ($session->sets as $setIndex => $set) {
                    $pairIndex = intdiv($setIndex, 2);
                    $reps = $firstTierReps - ($pairIndex * $this->repDecrement);
                    $reps = max($reps, $this->minimumReps);

                    $newSets[] = new ExerciseSet(
                        reps: $reps,
                        weight: $set->weight,
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

    protected function getAnchorRepsForWeek(int $weekIndex, int $blockLength): int
    {
        $reps = $this->startingReps - $this->repDecrement;
        $drops = intdiv($weekIndex, $this->stepDownInterval);
        $reps -= ($drops * $this->repDecrement);

        return max($reps, $this->minimumReps);
    }
}
