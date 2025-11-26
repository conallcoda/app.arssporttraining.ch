<?php

namespace App\Models\Training\Factory;

use App\Data\AbstractData;
use App\Models\Training\Data\BlockData;
use App\Models\Training\Data\ExerciseData;
use App\Models\Training\Data\SeasonData;
use App\Models\Training\Data\SessionData;
use App\Models\Training\Data\SetData;
use App\Models\Training\Data\WeekData;
use App\Models\Training\TrainingNode;

class SeasonFactory extends AbstractData
{
    public static function create(SeasonConfig $config): TrainingNode
    {
        $blocks = [];

        for ($blockIndex = 0; $blockIndex < $config->numberOfBlocks; $blockIndex++) {
            $weeks = [];

            for ($weekIndex = 0; $weekIndex < $config->weeksPerBlock; $weekIndex++) {
                $sessions = self::createSessions($config);
                $weeks[] = (new WeekData())->withChildren($sessions);
            }

            $blocks[] = (new BlockData())->withChildren($weeks);
        }

        $seasonData = (new SeasonData())->withChildren($blocks);

        $node = TrainingNode::fromData($seasonData);
        $node->name = $config->name;

        return $node;
    }

    protected static function createSessions(SeasonConfig $config): array
    {
        if ($config->sessionsPerWeek <= 0) {
            return [];
        }

        $sessions = [];
        $sessionIndex = 0;

        for ($day = 0; $day < 7 && $sessionIndex < $config->sessionsPerWeek; $day++) {
            for ($slot = 0; $slot < 2 && $sessionIndex < $config->sessionsPerWeek; $slot++) {
                $exercises = self::createExercises($config, $sessionIndex);

                $sessions[] = (new SessionData(
                    name: null,
                    day: $day,
                    slot: $slot,
                    category: $config->defaultCategoryId,
                    exercises: []
                ))->withChildren($exercises);

                $sessionIndex++;
            }
        }

        return $sessions;
    }

    protected static function createExercises(SeasonConfig $config, int $sessionIndex): array
    {
        if ($config->exercisesPerSession <= 0) {
            return [];
        }

        $exercises = [];
        $availableExerciseIds = $config->exerciseIds ?? [];

        for ($i = 0; $i < $config->exercisesPerSession; $i++) {
            $exerciseId = 0;
            if (!empty($availableExerciseIds)) {
                $exerciseId = $availableExerciseIds[($sessionIndex * $config->exercisesPerSession + $i) % count($availableExerciseIds)];
            }

            $sets = self::createSets($config);

            $exercises[] = (new ExerciseData(
                exercise: $exerciseId
            ))->withChildren($sets);
        }

        return $exercises;
    }

    protected static function createSets(SeasonConfig $config): array
    {
        if ($config->setsPerExercise <= 0) {
            return [];
        }

        $sets = [];

        for ($i = 0; $i < $config->setsPerExercise; $i++) {
            $sets[] = new SetData(
                reps: 10,
                weight: 0
            );
        }

        return $sets;
    }
}
