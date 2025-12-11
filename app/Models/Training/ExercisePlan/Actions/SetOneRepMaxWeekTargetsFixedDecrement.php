<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Data\Form\FluxField;
use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSession;
use App\Models\Training\ExercisePlan\ExerciseSet;
use App\Models\Training\ExercisePlan\ExerciseWeek;
use App\Models\Training\ExercisePlan\FixedWeightStep;

class SetOneRepMaxWeekTargetsFixedDecrement extends BlockAction
{
    public function __construct(
        public int $stepDownInterval = 2,
    ) {}

    public static function helpText(): string
    {
        return 'Sets weekly 1RM targets using fixed weight decrements from the final target, stepping down at specified intervals. Creates a reverse-engineered progression.';
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
                ->helpText('How many weeks to group together at the same 1RM target. Lower values create more frequent intensity changes.'),
        ];
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

        return $week->mapSessions(
            fn(ExerciseSession $session, int $sessionIndex) => $sessionIndex === $lastSessionIndex
                ? $session->mapSets(
                    fn(ExerciseSet $set, int $setIndex) => $setIndex === $lastSetIndex
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
