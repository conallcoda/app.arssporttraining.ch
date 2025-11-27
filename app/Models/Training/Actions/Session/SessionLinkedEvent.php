<?php

namespace App\Models\Training\Actions\Session;

use App\Models\Training\TrainingTree;
use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingNode;

class SessionLinkedEvent extends Action
{
    public function __construct(
        public TrainingNode $session,
        public ?string $oldLinkedTo,
        public ?string $newLinkedTo,
    ) {}

    public function redo(TrainingTree $tree)
    {
        $session = $tree->getNode($this->session->uuid);
        $session->linked_to = $this->newLinkedTo;
    }

    public function undo(TrainingTree $tree)
    {
        $session = $tree->getNode($this->session->uuid);
        $session->linked_to = $this->oldLinkedTo;
    }

    public function label()
    {
        if ($this->newLinkedTo) {
            return "Linked {$this->session->name()}";
        }
        return "Unlinked {$this->session->name()}";
    }
}
