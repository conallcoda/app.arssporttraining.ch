<?php

namespace App\Models\Training\Actions\Season;

use App\Models\Training\Data\BlockData;
use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingNode;

class RemoveBlock extends Action
{
    public function __construct(
        public string $parentId,
        public string $id,
    ) {}

    public function execute(TrainingNode $parent): self
    {
        $node = TrainingNode::fromData(new BlockData());
        $parent->children[] = $node;
        return $this;
    }
}
