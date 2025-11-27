<?php

namespace App\Models\Training\Actions\Week;

use App\Models\Training\TrainingTree;
use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingNode;

class WeekLinkedEvent extends Action
{
    public function __construct(
        public TrainingNode $week,
        public ?string $oldLinkedTo,
        public ?string $newLinkedTo,
    ) {}

    public function redo(TrainingTree $tree)
    {
        $week = $tree->getNode($this->week->uuid);
        $week->linked_to = $this->newLinkedTo;
    }

    public function undo(TrainingTree $tree)
    {
        $week = $tree->getNode($this->week->uuid);
        $week->linked_to = $this->oldLinkedTo;
    }

    public function label()
    {
        if ($this->newLinkedTo) {
            return "Linked {$this->week->name()}";
        }
        return "Unlinked {$this->week->name()}";
    }
}
