<?php

namespace App\Models\Training\Actions\Node;

use App\Models\Training\TrainingTree;
use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingNode;

class NodeDuplicatedEvent extends Action
{
    public function __construct(
        public TrainingNode $parent,
        public TrainingNode $original,
        public TrainingNode $duplicate,
        public int $index,
    ) {}

    public function redo(TrainingTree $tree)
    {
        $parent = $tree->getNode($this->parent->uuid);

        $index = null;
        foreach ($parent->children as $i => $child) {
            if ($child->uuid === $this->original->uuid) {
                $index = $i;
                break;
            }
        }

        if ($index !== null) {
            $duplicate = $tree::deepCloneNode($this->duplicate, generateNewUuid: false);
            array_splice($parent->children, $index + 1, 0, [$duplicate]);
            $tree->renumberChildren($parent->children);
        }
    }

    public function undo(TrainingTree $tree)
    {
        $parent = $tree->getNode($this->parent->uuid);
        $tree->removeChild($parent, $this->duplicate->uuid);
    }

    public function label()
    {
        return "Duplicated {$this->original->name} in {$this->parent->name}";
    }
}
