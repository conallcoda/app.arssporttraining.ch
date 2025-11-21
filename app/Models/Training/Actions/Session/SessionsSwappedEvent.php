<?php

namespace App\Models\Training\Actions\Session;

use App\Models\Training\TrainingTree;
use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingNode;

class SessionsSwappedEvent extends Action
{
    public function __construct(
        public TrainingNode $session1,
        public TrainingNode $session2,
    ) {}

    public function redo(TrainingTree $tree)
    {
        $session1 = $tree->getNode($this->session1->uuid);
        $session2 = $tree->getNode($this->session2->uuid);

        $temp1Day = $session1->data->day;
        $temp1Slot = $session1->data->slot;

        $session1->data->day = $session2->data->day;
        $session1->data->slot = $session2->data->slot;
        $session2->data->day = $temp1Day;
        $session2->data->slot = $temp1Slot;
    }

    public function undo(TrainingTree $tree)
    {
        $this->redo($tree);
    }

    public function label()
    {
        return "Swapped Sessions {$this->session1->name} and {$this->session2->name}";
    }
}
