<?php

namespace App\Models\Training\Actions\Week;

use App\Models\Training\TrainingTree;
use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingNode;

class WeekAddedEvent extends Action
{
    public function __construct(
        public TrainingNode $parent,
        public TrainingNode $child,
    ) {}

    public function redo(TrainingTree $tree)
    {
        $parent = $tree->getNode($this->parent->uuid);
        $tree->addChild($parent, $this->child->data);
    }

    public function undo(TrainingTree $tree)
    {
        $parent = $tree->getNode($this->parent->uuid);
        $tree->removeChild($parent, $this->child->uuid);
    }

    public function label()
    {
        return "Added Week to {$this->parent->name}";
    }
}
