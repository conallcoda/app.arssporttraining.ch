<?php

namespace App\Models\Training\Factory;

use App\Data\AbstractData;
use App\Models\Exercise\Exercise;
use App\Models\Training\Data\BlockData;
use App\Models\Training\Data\SeasonData;
use App\Models\Training\Data\SessionData;
use App\Models\Training\Data\WeekData;
use App\Models\Training\TrainingNode;

class LargeSeasonFactory extends AbstractData
{
    public static function create(SeasonConfig $config): TrainingNode
    {
        $exerciseIds = Exercise::pluck('id')->toArray();

        if (empty($exerciseIds)) {
            throw new \RuntimeException('No exercises found. Please create some exercises first.');
        }

        $blocks = [];

        for ($blockIndex = 0; $blockIndex < $config->numberOfBlocks; $blockIndex++) {
            $weeks = [];

            for ($weekIndex = 0; $weekIndex < $config->weeksPerBlock; $weekIndex++) {
                $sessions = [];

                for ($day = 0; $day < 7; $day++) {
                    for ($slot = 0; $slot < 2; $slot++) {
                        $randomExercises = [];

                        for ($exerciseIndex = 0; $exerciseIndex < 6; $exerciseIndex++) {
                            $randomExercises[] = $exerciseIds[array_rand($exerciseIds)];
                        }

                        $sessions[] = new SessionData(
                            name: null,
                            day: $day,
                            slot: $slot,
                            exercises: $randomExercises
                        );
                    }
                }

                $weeks[] = (new WeekData)->withChildren($sessions);
            }

            $blocks[] = (new BlockData)->withChildren($weeks);
        }

        $seasonData = (new SeasonData)->withChildren($blocks);

        $node = TrainingNode::fromData($seasonData);
        $node->name = $config->name;

        return $node;
    }
}
