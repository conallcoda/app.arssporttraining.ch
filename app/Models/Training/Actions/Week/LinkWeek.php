<?php

namespace App\Models\Training\Actions\Week;

use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingTree;

class LinkWeek extends Action
{
    public function __construct(
        public string $weekId,
        public ?string $linkedTo = null,
    ) {}

    public function execute(TrainingTree $tree): WeekLinkedEvent
    {
        $week = $tree->getNode($this->weekId);

        if (!$week) {
            throw new \Exception("Week node not found");
        }

        $oldLinkedTo = $week->linked_to;
        $week->linked_to = $this->linkedTo ?: null;

        return WeekLinkedEvent::from([
            'week' => $week,
            'oldLinkedTo' => $oldLinkedTo,
            'newLinkedTo' => $week->linked_to,
        ]);
    }
}
