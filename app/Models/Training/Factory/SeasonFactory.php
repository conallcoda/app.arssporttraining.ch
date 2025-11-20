<?php

namespace App\Models\Training\Factory;

use App\Data\AbstractData;
use App\Models\Training\Data\BlockData;
use App\Models\Training\Data\SeasonData;
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
                $weeks[] = new WeekData();
            }

            $blocks[] = (new BlockData())->withChildren($weeks);
        }

        $seasonData = (new SeasonData())->withChildren($blocks);

        $node = TrainingNode::fromData($seasonData);
        $node->name = $config->name;

        return $node;
    }
}
