<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Data\Form\FluxField;
use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSession;
use App\Models\Training\ExercisePlan\ExerciseSet;
use App\Models\Training\ExercisePlan\ExerciseWeek;
use App\Models\Training\ExercisePlan\FixedWeightStep;

class SetWeightWeekTargetsFixedDecrement extends BlockAction
{
    public function __construct(
        public int $stepDownInterval = 1,
    ) {}

    public static function helpText(): string
    {
        return 'Sets weekly weight targets using fixed decrements from the final 1RM, working backwards. The last week uses the 1RM as weight, each prior week decrements.';
    }

    public static function getFields(): array
    {
        return [
            FluxField::number('stepDownInterval')
                ->label('Step Down Interval')
                ->required()
                ->min(1)
                ->max(10)
                ->suffix('weeks')
                ->rules('required|integer|min:1|max:10')
                ->helpText('How many weeks to group together at the same weight target. Lower values create more frequent intensity changes.'),
        ];
    }

    public function apply(ExerciseBlock $block): BlockResult
    {
        $lastWeekIndex = $block->lastWeekIndex();
        $lastSet = $block->lastWeek()->lastSession()->lastSet();
        $targetOneRepMax = $lastSet->oneRepMax ?? $block->config->targetOneRepMax;

        $weekWeights = $this->calculateWeekWeights($lastWeekIndex, $targetOneRepMax);

        $newWeeks = [];

        foreach ($block->weeks as $weekIndex => $week) {
            if ($weekIndex === $lastWeekIndex) {
                $newWeeks[] = $this->applyToWeek($week, $targetOneRepMax);

                continue;
            }

            $newWeeks[] = $this->applyToWeek($week, $weekWeights[$weekIndex]);
        }

        return $this->result($block, $block->withWeeks($newWeeks));
    }

    protected function calculateWeekWeights(int $lastWeekIndex, float $targetOneRepMax): array
    {
        $weekWeights = [];
        $weekWeights[$lastWeekIndex] = $targetOneRepMax;

        $currentWeight = $targetOneRepMax;

        for ($weekIndex = $lastWeekIndex - 1; $weekIndex >= 0; $weekIndex--) {
            $weeksFromTarget = $lastWeekIndex - $weekIndex;

            if ($weeksFromTarget % $this->stepDownInterval === 0) {
                $currentWeight = FixedWeightStep::decrement($currentWeight);
            }

            $weekWeights[$weekIndex] = $currentWeight;
        }

        return $weekWeights;
    }

    protected function applyToWeek(ExerciseWeek $week, float $weight): ExerciseWeek
    {
        $lastSessionIndex = $week->lastSessionIndex();
        $lastSetIndex = $week->lastSession()->lastSetIndex();

        return $week->mapSessions(
            fn (ExerciseSession $session, int $sessionIndex) => $sessionIndex === $lastSessionIndex
                ? $session->mapSets(
                    fn (ExerciseSet $set, int $setIndex) => $setIndex === $lastSetIndex
                        ? new ExerciseSet(
                            reps: $set->reps,
                            weight: $weight,
                            oneRepMax: $set->oneRepMax,
                        )
                        : $set
                )
                : $session
        );
    }
}
