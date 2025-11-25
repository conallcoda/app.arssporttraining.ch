<?php

namespace App\Models\Training\Actions\Set;

use App\Models\Training\Data\SetData;
use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingTree;

class AddSet extends Action
{
    public function __construct(
        public string $parentId,
        public int $reps,
        public float $weight,
    ) {}

    public function execute(TrainingTree $tree): SetAddedEvent
    {
        $parent = $tree->getNode($this->parentId);

        if (!$parent) {
            throw new \Exception("Parent node not found");
        }

        $setData = new SetData(
            reps: $this->reps,
            weight: $this->weight
        );

        $node = $tree->addChild($parent, $setData);
        return SetAddedEvent::from(['parent' => $parent, 'child' => $node]);
    }
}
