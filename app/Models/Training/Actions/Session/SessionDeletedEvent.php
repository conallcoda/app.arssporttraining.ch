<?php

namespace App\Models\Training\Actions\Session;

use App\Models\Training\TrainingTree;
use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingNode;

class SessionDeletedEvent extends Action
{
    public function __construct(
        public TrainingNode $parent,
        public TrainingNode $session,
    ) {}

    public function redo(TrainingTree $tree)
    {
        $parent = $tree->getNode($this->parent->uuid);

        if ($this->session->id) {
            $tree->deletedNodes[] = $this->session->uuid;
        }

        $tree->removeChild($parent, $this->session->uuid);
    }

    public function undo(TrainingTree $tree)
    {
        $parent = $tree->getNode($this->parent->uuid);
        $tree->addChild($parent, $this->session);

        $key = array_search($this->session->uuid, $tree->deletedNodes);
        if ($key !== false) {
            unset($tree->deletedNodes[$key]);
        }
    }

    public function label()
    {
        return "Deleted Session from {$this->parent->name()}";
    }
}
