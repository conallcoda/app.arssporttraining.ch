<?php

namespace App\Models\Training\Operations\Season;

use App\Models\Training\Data\BlockData;
use App\Models\Training\Operations\OperationData;
use App\Models\Training\TrainingNode;

class AddBlock extends OperationData
{

    public function __construct(
        public string $parentId,
        public string $id,
    ) {}

    public static function do(TrainingNode $parent): TrainingNode
    {
        $node = TrainingNode::fromData(new BlockData());
        $node->children[] = [];
        return $parent;
    }

    public function undo() {}
}
