<?php

namespace App\Models\Training\Actions\Set;

use App\Models\Training\Data\SetData;
use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingTree;

class UpdateSet extends Action
{
    public function __construct(
        public string $setId,
        public int $reps,
        public float $weight,
    ) {}

    public function execute(TrainingTree $tree): SetUpdatedEvent
    {
        $node = $tree->getNode($this->setId);

        if (!$node) {
            throw new \Exception("Set node not found");
        }

        $oldData = $node->data;

        $node->data = new SetData(
            reps: $this->reps,
            weight: $this->weight
        );

        return SetUpdatedEvent::from([
            'node' => $node,
            'oldData' => $oldData,
            'newData' => $node->data,
        ]);
    }
}
