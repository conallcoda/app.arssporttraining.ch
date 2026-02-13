<?php

namespace App\Training\Strategies;

use App\Training\Data\ExerciseConfig;
use App\Training\Data\TrainingBlock;
use App\Training\Data\TrainingSession;
use App\Training\Data\TrainingSet;
use App\Training\Data\TrainingWeek;
use App\Training\Services\DeloadRule;
use App\Training\Services\PairedRepCalculator;
use App\Training\Services\WeightStep;

class FixedDecrementStrategy implements StrategyInterface
{
    public function __construct(
        protected int $weeklyStepDownInterval = 1,
        protected int $setStepDownInterval = 1,
        protected int $repStepDownInterval = 2,
        protected int $repDecrement = 2,
        protected int $minimumReps = 1,
        protected float $roundingStep = 0.5,
    ) {}

    public function generate(ExerciseConfig $config, int $weeks): TrainingBlock
    {
        $weekTargets = $this->calculateWeekTargets($config->target1RM, $weeks);

        $repCalculator = new PairedRepCalculator(
            startingReps: $config->startingReps,
            stepDownInterval: $this->repStepDownInterval,
            repDecrement: $this->repDecrement,
            minimumReps: $this->minimumReps,
        );

        $deloadRule = $config->deloadEnabled
            ? new DeloadRule(setsToReduceBy: $config->deloadSetsReduction)
            : null;

        $trainingWeeks = [];

        for ($weekIndex = 0; $weekIndex < $weeks; $weekIndex++) {
            $weekTarget = $weekTargets[$weekIndex];

            $actualSets = $deloadRule
                ? $deloadRule->getSetsForWeek($weekIndex, $config->sets)
                : $config->sets;

            $setWeights = $this->calculateSetWeights($weekTarget, $actualSets);

            $sessions = [];
            $lastSetIndex = $actualSets - 1;
            $isLastWeek = $weekIndex === $weeks - 1;

            for ($sessionIndex = 0; $sessionIndex < $config->sessionsPerWeek; $sessionIndex++) {
                $sets = [];
                for ($setIndex = 0; $setIndex < $actualSets; $setIndex++) {
                    $reps = $repCalculator->getRepsForSet($weekIndex, $setIndex);
                    $weight = WeightStep::round($setWeights[$setIndex], $this->roundingStep);

                    $isLastSet = $setIndex === $lastSetIndex;
                    $oneRepMax = ($isLastWeek && $isLastSet) ? round($weekTarget, 1) : null;

                    $sets[] = new TrainingSet(
                        reps: $reps,
                        weight: $weight,
                        oneRepMax: $oneRepMax,
                    );
                }
                $sessions[] = new TrainingSession(sets: $sets);
            }

            $trainingWeeks[] = new TrainingWeek(
                sessions: $sessions,
                target: $weekTarget,
            );
        }

        return new TrainingBlock(
            config: $config,
            weeks: $trainingWeeks,
        );
    }

    protected function calculateWeekTargets(float $target1RM, int $weeks): array
    {
        $targets = [];
        $lastWeekIndex = $weeks - 1;
        $targets[$lastWeekIndex] = $target1RM;

        $currentWeight = $target1RM;

        for ($weekIndex = $lastWeekIndex - 1; $weekIndex >= 0; $weekIndex--) {
            $weeksFromTarget = $lastWeekIndex - $weekIndex;

            if ($weeksFromTarget % $this->weeklyStepDownInterval === 0) {
                $currentWeight = WeightStep::decrement($currentWeight);
            }

            $targets[$weekIndex] = $currentWeight;
        }

        ksort($targets);

        return $targets;
    }

    protected function calculateSetWeights(float $weekTarget, int $setCount): array
    {
        $weights = [];
        $totalGroups = (int) ceil($setCount / $this->setStepDownInterval);

        for ($setIndex = 0; $setIndex < $setCount; $setIndex++) {
            $groupFromStart = intdiv($setIndex, $this->setStepDownInterval);
            $groupFromEnd = $totalGroups - 1 - $groupFromStart;
            $weight = $weekTarget;

            for ($i = 0; $i < $groupFromEnd; $i++) {
                $weight = WeightStep::decrement($weight);
            }

            $weights[] = $weight;
        }

        return $weights;
    }

    protected function calculate1RM(float $weight, int $reps): float
    {
        $percentage = \App\Training\Reference\RepPercentageTable::getPercentage($reps);

        return $weight / $percentage;
    }
}
