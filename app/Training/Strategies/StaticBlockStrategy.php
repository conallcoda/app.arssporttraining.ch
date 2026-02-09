<?php

namespace App\Training\Strategies;

use App\Training\Data\ExerciseConfig;
use App\Training\Data\TrainingBlock;
use App\Training\Data\TrainingSession;
use App\Training\Data\TrainingSet;
use App\Training\Data\TrainingWeek;

class StaticBlockStrategy implements StrategyInterface
{
    public function __construct(
        protected TrainingSet $templateSet,
        protected int $sets = 1,
    ) {}

    public function generate(ExerciseConfig $config, int $weeks): TrainingBlock
    {
        $trainingWeeks = [];

        for ($weekIndex = 0; $weekIndex < $weeks; $weekIndex++) {
            $sessions = [];

            for ($sessionIndex = 0; $sessionIndex < $config->sessionsPerWeek; $sessionIndex++) {
                $sets = [];
                for ($setIndex = 0; $setIndex < $this->sets; $setIndex++) {
                    $sets[] = clone $this->templateSet;
                }
                $sessions[] = new TrainingSession(sets: $sets);
            }

            $trainingWeeks[] = new TrainingWeek(sessions: $sessions);
        }

        return new TrainingBlock(
            config: $config,
            weeks: $trainingWeeks,
        );
    }
}
