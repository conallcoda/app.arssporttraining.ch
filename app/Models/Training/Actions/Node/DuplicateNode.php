<?php

namespace App\Models\Training\Actions\Node;

use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingTree;

class DuplicateNode extends Action
{
    public function __construct(
        public string $nodeId,
    ) {}

    public function execute(TrainingTree $tree): NodeDuplicatedEvent
    {
        $parent = $tree->findParentNode($this->nodeId);
        $node = $tree->getNode($this->nodeId);

        if (! $parent || ! $node) {
            throw new \Exception('Parent or node not found');
        }

        $index = $tree->findChildIndex($parent, $this->nodeId);

        if ($index === null) {
            throw new \Exception("Node not found in parent's children");
        }

        $duplicate = $tree::deepCloneNode($node, generateNewUuid: true);
        array_splice($parent->children, $index + 1, 0, [$duplicate]);
        $tree->renumberChildren($parent->children);

        return NodeDuplicatedEvent::from([
            'parent' => $parent,
            'original' => $node,
            'duplicate' => $duplicate,
            'index' => $index,
        ]);
    }

    public static function fromNodeId(string $nodeId): self
    {
        return new self(nodeId: $nodeId);
    }
}
