<?php

namespace App\Models\Training\Actions\Session;

use App\Models\Training\TrainingTree;
use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingNode;

class SessionMovedEvent extends Action
{
    public function __construct(
        public TrainingNode $parent,
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
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $slots = ['Morning', 'Afternoon'];

        $oldDayName = $days[$this->oldDay] ?? "Day {$this->oldDay}";
        $newDayName = $days[$this->newDay] ?? "Day {$this->newDay}";
        $oldSlotName = $slots[$this->oldSlot] ?? "Slot {$this->oldSlot}";
        $newSlotName = $slots[$this->newSlot] ?? "Slot {$this->newSlot}";

        return "Moved Session in {$this->parent->name()} from {$oldDayName} {$oldSlotName} to {$newDayName} {$newSlotName}";
    }
}
