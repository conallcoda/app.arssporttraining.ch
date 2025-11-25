<?php

namespace App\Models\Training\Actions\Exercise;

use App\Models\Training\Data\ExerciseData;
use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingTree;

class UpdateExercise extends Action
{
    public function __construct(
        public string $exerciseId,
        public int $exercise,
    ) {}

    public function execute(TrainingTree $tree): ExerciseUpdatedEvent
    {
        $node = $tree->getNode($this->exerciseId);

        if (!$node) {
            throw new \Exception("Exercise node not found");
        }

        $oldData = $node->data;

        $node->data = new ExerciseData(
            exercise: $this->exercise
        );

        return ExerciseUpdatedEvent::from([
            'node' => $node,
            'oldData' => $oldData,
            'newData' => $node->data,
        ]);
    }
}
