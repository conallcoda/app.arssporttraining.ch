<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Data\Form\FluxField;
use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSession;
use App\Models\Training\ExercisePlan\ExerciseSet;
use App\Models\Training\ExercisePlan\FixedWeightStep;

class SetWeekWeightProgressionFixedDecrement extends BlockAction
{
    public function __construct(
        public int $stepDownInterval = 1,
    ) {}

    public static function helpText(): string
    {
        return 'Distributes weight values across sets within each week using fixed decrements, grouping sets at specified intervals with the same weight.';
    }

    public static function getFields(): array
    {
        return [
            FluxField::number('stepDownInterval')
                ->label('Step Down Interval')
                ->required()
                ->min(1)
                ->max(10)
                ->suffix('sets')
                ->rules('required|integer|min:1|max:10')
                ->helpText('How many sets to group at the same weight before decreasing. A value of 2 means sets 1-2 share weight, then 3-4, etc.'),
        ];
    }

    public function apply(ExerciseBlock $block): BlockResult
    {
        $newBlock = $block->mapWeeks(fn ($week) => $this->applyToWeek($week));

        return $this->result($block, $newBlock);
    }

    protected function applyToWeek($week)
    {
        $lastSessionIndex = $week->lastSessionIndex();
        $lastSession = $week->lastSession();
        $lastSet = $lastSession->lastSet();
        $setCount = count($lastSession->sets);
        $totalGroups = (int) ceil($setCount / $this->stepDownInterval);

        return $week->mapSessions(
            fn (ExerciseSession $session, int $sessionIndex) => $sessionIndex === $lastSessionIndex
                ? $session->mapSets(
                    fn (ExerciseSet $set, int $setIndex) => $this->applyToSet($set, $setIndex, $lastSet->weight, $totalGroups)
                )
                : $session
        );
    }

    protected function applyToSet(ExerciseSet $set, int $setIndex, ?float $targetWeight, int $totalGroups): ExerciseSet
    {
        $groupFromStart = intdiv($setIndex, $this->stepDownInterval);
        $groupFromEnd = $totalGroups - 1 - $groupFromStart;
        $weightForSet = $targetWeight;

        for ($i = 0; $i < $groupFromEnd; $i++) {
            $weightForSet = FixedWeightStep::decrement($weightForSet);
        }

        return new ExerciseSet(
            reps: $set->reps,
            weight: $weightForSet,
            oneRepMax: $set->oneRepMax,
        );
    }
}
