<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Data\Form\FluxField;
use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSession;
use App\Models\Training\ExercisePlan\ExerciseSet;
use App\Models\Training\ExercisePlan\ExerciseWeek;

class SetOneRepMaxWeekTargetsLinear extends BlockAction
{
    public function __construct(
        protected int $stepUpInterval = 1,
    ) {}

    public static function getFields(): array
    {
        return [
            FluxField::number('stepUpInterval')
                ->label('Step Up Interval')
                ->required()
                ->min(1)
                ->max(10)
                ->suffix('weeks')
                ->rules('required|integer|min:1|max:10'),
        ];
    }

    public function apply(ExerciseBlock $block): BlockResult
    {
        $startingOneRepMax = $block->config->startingOneRepMax;
        $targetOneRepMax = $block->config->targetOneRepMax;
        $weekCount = count($block->weeks);

        $stepCount = $this->countSteps($weekCount);
        $stepSize = $stepCount > 0 ? ($targetOneRepMax - $startingOneRepMax) / $stepCount : 0;

        $newWeeks = [];

        foreach ($block->weeks as $weekIndex => $week) {
            $stepsForWeek = $this->countStepsUpToWeek($weekIndex);
            $weekOneRepMax = $startingOneRepMax + ($stepSize * $stepsForWeek);

            $newWeeks[] = $this->applyToWeek($week, $weekOneRepMax);
        }

        return $this->result($block, $block->withWeeks($newWeeks));
    }

    protected function applyToWeek(ExerciseWeek $week, float $currentOneRepMax): ExerciseWeek
    {
        $lastSessionIndex = $week->lastSessionIndex();
        $lastSetIndex = $week->lastSession()->lastSetIndex();

        return $week->mapSessions(
            fn (ExerciseSession $session, int $sessionIndex) => $sessionIndex === $lastSessionIndex
                ? $session->mapSets(
                    fn (ExerciseSet $set, int $setIndex) => $setIndex === $lastSetIndex
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

    protected function countSteps(int $weekCount): int
    {
        $count = 0;

        for ($weekIndex = 0; $weekIndex < $weekCount; $weekIndex++) {
            if (($weekIndex + 1) % $this->stepUpInterval === 0) {
                $count++;
            }
        }

        return $count;
    }

    protected function countStepsUpToWeek(int $targetWeekIndex): int
    {
        $count = 0;

        for ($weekIndex = 0; $weekIndex <= $targetWeekIndex; $weekIndex++) {
            if (($weekIndex + 1) % $this->stepUpInterval === 0) {
                $count++;
            }
        }

        return $count;
    }
}
