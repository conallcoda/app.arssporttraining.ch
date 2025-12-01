<?php

namespace App\Models\Training\Actions\Node;

use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingTree;

class NodeMovedEvent extends Action
{
    public function __construct(
        public TrainingNode $parent,
        public TrainingNode $node,
        public int $oldIndex,
        public int $newIndex,
        public int $direction,
    ) {}

    public function redo(TrainingTree $tree)
    {
        $this->moveNode($tree, $this->direction);
    }

    public function undo(TrainingTree $tree)
    {
        $this->moveNode($tree, $this->direction * -1);
    }

    protected function moveNode(TrainingTree $tree, int $direction): void
    {
        $parent = $tree->getNode($this->parent->uuid);

        if (empty($parent->children)) {
            return;
        }

        $currentIndex = $tree->findChildIndex($parent, $this->node->uuid);

        if ($currentIndex === null) {
            return;
        }

        $targetIndex = $currentIndex + $direction;

        if ($targetIndex >= 0 && $targetIndex < count($parent->children)) {
            $currentNode = $parent->children[$currentIndex];
            $targetNode = $parent->children[$targetIndex];

            $tempSequence = $currentNode->sequence;
            $currentNode->sequence = $targetNode->sequence;
            $targetNode->sequence = $tempSequence;

            $parent->children[$currentIndex] = $targetNode;
            $parent->children[$targetIndex] = $currentNode;
        }
    }

    public function label()
    {
        $direction = $this->direction > 0 ? 'down' : 'up';

        return "Moved {$this->node->name()} {$direction} in {$this->parent->name()}";
    }
}
