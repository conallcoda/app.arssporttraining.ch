<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSet;

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

        $newBlock = $block->mapWeeks(fn ($week, int $weekIndex) => $week->mapSessions(fn ($session) => $this->applyToSession($session, $weekIndex, $blockLength))
        );

        return $this->result($block, $newBlock);
    }

    protected function applyToSession($session, int $weekIndex, int $blockLength)
    {
        $anchorReps = $this->getAnchorRepsForWeek($weekIndex, $blockLength);
        $firstTierReps = $anchorReps + $this->repDecrement;

        return $session->mapSets(fn (ExerciseSet $set, int $setIndex) => new ExerciseSet(
            reps: max($firstTierReps - (intdiv($setIndex, 2) * $this->repDecrement), $this->minimumReps),
            weight: $set->weight,
            oneRepMax: $set->oneRepMax,
        )
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
