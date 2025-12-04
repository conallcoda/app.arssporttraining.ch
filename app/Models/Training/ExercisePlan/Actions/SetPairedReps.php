<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Data\Form\FluxField;
use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSet;

class SetPairedReps extends BlockAction
{
    public function __construct(
        public int $startingReps = 12,
        public int $stepDownInterval = 2,
        public int $repDecrement = 2,
        public int $minimumReps = 1,
    ) {}

    public static function getFields(): array
    {
        return [
            FluxField::number('startingReps')
                ->label('Starting Reps')
                ->required()
                ->min(1)
                ->max(30)
                ->suffix('reps')
                ->rules('required|integer|min:1|max:30'),
            FluxField::number('stepDownInterval')
                ->label('Step Down Interval')
                ->required()
                ->min(1)
                ->max(10)
                ->suffix('weeks')
                ->rules('required|integer|min:1|max:10'),
            FluxField::number('repDecrement')
                ->label('Rep Decrement')
                ->required()
                ->min(1)
                ->max(10)
                ->suffix('reps')
                ->rules('required|integer|min:1|max:10'),
            FluxField::number('minimumReps')
                ->label('Minimum Reps')
                ->required()
                ->min(1)
                ->max(20)
                ->suffix('reps')
                ->rules('required|integer|min:1|max:20'),
        ];
    }

    public function apply(ExerciseBlock $block): BlockResult
    {
        $blockLength = count($block->weeks);

        $newBlock = $block->mapWeeks(
            fn ($week, int $weekIndex) => $week->mapSessions(fn ($session) => $this->applyToSession($session, $weekIndex, $blockLength))
        );

        return $this->result($block, $newBlock);
    }

    protected function applyToSession($session, int $weekIndex, int $blockLength)
    {
        $anchorReps = $this->getAnchorRepsForWeek($weekIndex, $blockLength);
        $firstTierReps = $anchorReps + $this->repDecrement;

        return $session->mapSets(
            fn (ExerciseSet $set, int $setIndex) => new ExerciseSet(
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
