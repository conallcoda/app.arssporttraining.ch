<?php

namespace App\Models\Training\Actions\Exercise;

use App\Models\Training\TrainingTree;
use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingNode;
use App\Models\Training\Data\ExerciseData;

class ExerciseUpdatedEvent extends Action
{
    public function __construct(
        public TrainingNode $node,
        public ExerciseData $oldData,
        public ExerciseData $newData,
    ) {}

    public function redo(TrainingTree $tree)
    {
        $node = $tree->getNode($this->node->uuid);
        $node->data = $this->newData;
    }

    public function undo(TrainingTree $tree)
    {
        $node = $tree->getNode($this->node->uuid);
        $node->data = $this->oldData;
    }

    public function label()
    {
        return "Updated {$this->node->name()}";
    }
}
