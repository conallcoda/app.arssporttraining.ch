<?php

namespace App\Models\Training\Actions\Node;

use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingTree;

class DeleteNode extends Action
{
    public function __construct(
        public string $nodeId,
    ) {}

    public function execute(TrainingTree $tree): NodeDeletedEvent
    {
        $node = $tree->getNode($this->nodeId);
        $parent = $tree->findParentNode($this->nodeId);

        if (!$node) {
            throw new \Exception("Node not found");
        }

        $tree->deletedNodes[] = $this->nodeId;

        if ($parent) {
            $tree->removeChild($parent->uuid, $this->nodeId);
        }

        return NodeDeletedEvent::from([
            'parent' => $parent,
            'node' => $node
        ]);
    }

    public static function fromNodeId(string $nodeId): self
    {
        return new self(nodeId: $nodeId);
    }
}
