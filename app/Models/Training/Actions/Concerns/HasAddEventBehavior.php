<?php

namespace App\Models\Training\Actions\Concerns;

use App\Models\Training\TrainingTree;

trait HasAddEventBehavior
{
    public function redo(TrainingTree $tree): void
    {
        $parent = $tree->getNode($this->parent->uuid);
        $tree->addChild($parent, $this->child->data);
    }

    public function undo(TrainingTree $tree): void
    {
        $parent = $tree->getNode($this->parent->uuid);
        $tree->removeChild($parent, $this->child->uuid);
    }

    public function label(): string
    {
        return "Added {$this->child->name()} to {$this->parent->name()}";
    }
}
