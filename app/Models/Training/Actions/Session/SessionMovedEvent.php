<?php

namespace App\Models\Training\Actions\Session;

use App\Models\Training\TrainingTree;
use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingNode;

class SessionMovedEvent extends Action
{
    public function __construct(
        public TrainingNode $session,
        public int $oldDay,
        public int $oldSlot,
        public int $newDay,
        public int $newSlot,
    ) {}

    public function redo(TrainingTree $tree)
    {
        $session = $tree->getNode($this->session->uuid);
        $session->data->day = $this->newDay;
        $session->data->slot = $this->newSlot;
    }

    public function undo(TrainingTree $tree)
    {
        $session = $tree->getNode($this->session->uuid);
        $session->data->day = $this->oldDay;
        $session->data->slot = $this->oldSlot;
    }

    public function label()
    {
        return "Moved Session from Day {$this->oldDay} Slot {$this->oldSlot} to Day {$this->newDay} Slot {$this->newSlot}";
    }
}
