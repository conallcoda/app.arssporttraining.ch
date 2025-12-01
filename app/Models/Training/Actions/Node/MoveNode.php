<?php

namespace App\Models\Training\Actions\Node;

use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingTree;

class MoveNode extends Action
{
    public function __construct(
        public string $nodeId,
        public int $direction,
    ) {}

    public function execute(TrainingTree $tree): NodeMovedEvent
    {
        $parent = $tree->findParentNode($this->nodeId);
        $node = $tree->getNode($this->nodeId);

        if (! $parent || ! $node || empty($parent->children)) {
            throw new \Exception('Parent or node not found, or parent has no children');
        }

        $index = $tree->findChildIndex($parent, $this->nodeId);

        if ($index === null) {
            throw new \Exception("Node not found in parent's children");
        }

        $targetIndex = $index + $this->direction;

        if ($targetIndex < 0 || $targetIndex >= count($parent->children)) {
            throw new \Exception("Cannot move node beyond parent's boundaries");
        }

        $currentNode = $parent->children[$index];
        $targetNode = $parent->children[$targetIndex];

        $tempSequence = $currentNode->sequence;
        $currentNode->sequence = $targetNode->sequence;
        $targetNode->sequence = $tempSequence;

        $parent->children[$index] = $targetNode;
        $parent->children[$targetIndex] = $currentNode;

        return NodeMovedEvent::from([
            'parent' => $parent,
            'node' => $node,
            'oldIndex' => $index,
            'newIndex' => $targetIndex,
            'direction' => $this->direction,
        ]);
    }

    public static function fromNodeId(string $nodeId, int $direction): self
    {
        return new self(
            nodeId: $nodeId,
            direction: $direction
        );
    }
}
