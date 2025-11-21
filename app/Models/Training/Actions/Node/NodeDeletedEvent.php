<?php

namespace App\Models\Training\Actions\Node;

use App\Models\Training\TrainingTree;
use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingNode;

class NodeDeletedEvent extends Action
{
    public function __construct(
        public ?TrainingNode $parent,
        public TrainingNode $node,
    ) {}

    public function redo(TrainingTree $tree)
    {
        $tree->deletedNodes[] = $this->node->uuid;

        if ($this->parent) {
            $parent = $tree->getNode($this->parent->uuid);
            $tree->removeChild($parent, $this->node->uuid);
        }
    }

    public function undo(TrainingTree $tree)
    {
        if ($this->parent) {
            $parent = $tree->getNode($this->parent->uuid);
            $tree->addChild($parent, $this->node);
        }

        $key = array_search($this->node->uuid, $tree->deletedNodes);
        if ($key !== false) {
            unset($tree->deletedNodes[$key]);
        }
    }

    public function label()
    {
        $parentName = $this->parent ? $this->parent->name() : 'root';
        return "Deleted {$this->node->name()} from {$parentName}";
    }
}
