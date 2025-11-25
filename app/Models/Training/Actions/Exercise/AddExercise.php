<?php

namespace App\Models\Training\Actions\Exercise;

use App\Models\Training\Data\ExerciseData;
use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingTree;

class AddExercise extends Action
{
    public function __construct(
        public string $parentId,
        public int $exercise,
    ) {}

    public function execute(TrainingTree $tree): ExerciseAddedEvent
    {
        $parent = $tree->getNode($this->parentId);

        if (!$parent) {
            throw new \Exception("Parent node not found");
        }

        $exerciseData = new ExerciseData(
            exercise: $this->exercise
        );

        $node = $tree->addChild($parent, $exerciseData);
        return ExerciseAddedEvent::from(['parent' => $parent, 'child' => $node]);
    }
}
