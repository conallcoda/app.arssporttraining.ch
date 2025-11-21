<?php

namespace App\Models\Training\Actions\Node;

use App\Models\Training\TrainingTree;
use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingNode;

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
        $parent = $tree->getNode($this->parent->uuid);

        if (empty($parent->children)) {
            return;
        }

        $currentIndex = null;
        foreach ($parent->children as $i => $child) {
            if ($child->uuid === $this->node->uuid) {
                $currentIndex = $i;
                break;
            }
        }

        if ($currentIndex === null) {
            return;
        }

        $targetIndex = $currentIndex + $this->direction;

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

    public function undo(TrainingTree $tree)
    {
        $parent = $tree->getNode($this->parent->uuid);

        if (empty($parent->children)) {
            return;
        }

        $currentIndex = null;
        foreach ($parent->children as $i => $child) {
            if ($child->uuid === $this->node->uuid) {
                $currentIndex = $i;
                break;
            }
        }

        if ($currentIndex === null) {
            return;
        }

        $targetIndex = $currentIndex + ($this->direction * -1);

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
        return "Moved {$this->node->name} {$direction} in {$this->parent->name}";
    }
}
