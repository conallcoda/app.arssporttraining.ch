<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Data\Form\FluxField;
use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSession;
use App\Models\Training\ExercisePlan\ExerciseSet;
use App\Models\Training\ExercisePlan\FixedWeightStep;

class SetWeekOneRepMaxProgressionFixedDecrement extends BlockAction
{
    public function __construct(
        protected int $stepDownInterval = 2,
    ) {}

    public static function getFields(): array
    {
        return [
            FluxField::number('stepDownInterval')
                ->label('Step Down Interval')
                ->required()
                ->min(1)
                ->max(10)
                ->suffix('sets')
                ->rules('required|integer|min:1|max:10'),
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

        return $week->mapSessions(fn (ExerciseSession $session, int $sessionIndex) => $sessionIndex === $lastSessionIndex
                ? $session->mapSets(fn (ExerciseSet $set, int $setIndex) => $this->applyToSet($set, $setIndex, $lastSet->oneRepMax, $totalGroups)
                )
                : $session
        );
    }

    protected function applyToSet(ExerciseSet $set, int $setIndex, ?float $targetOneRepMax, int $totalGroups): ExerciseSet
    {
        $groupFromStart = intdiv($setIndex, $this->stepDownInterval);
        $groupFromEnd = $totalGroups - 1 - $groupFromStart;
        $oneRepMaxForSet = $targetOneRepMax;

        for ($i = 0; $i < $groupFromEnd; $i++) {
            $oneRepMaxForSet = FixedWeightStep::decrement($oneRepMaxForSet);
        }

        return new ExerciseSet(
            reps: $set->reps,
            weight: $set->weight,
            oneRepMax: $oneRepMaxForSet,
        );
    }
}
