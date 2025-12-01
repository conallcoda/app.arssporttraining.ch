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

        if (! $week) {
            throw new \Exception('Week node not found');
        }

        $oldLinkedTo = $week->linked_to;
        $isUnlinking = $oldLinkedTo !== null && ($this->linkedTo === null || $this->linkedTo === '');

        if ($isUnlinking) {
            $sourceWeek = $tree->getNode($oldLinkedTo);

            if (! $sourceWeek) {
                throw new \InvalidArgumentException('Source week no longer exists');
            }

            $sourceSessions = $sourceWeek->children;

            foreach ($sourceSessions as $sourceSession) {
                $originalSource = $sourceSession->linked_to !== null
                    ? $sourceSession->linked_to
                    : $sourceSession->uuid;

                $linkedClone = $sourceSession->createLinkedClone($week->uuid, count($week->children));
                $linkedClone->linked_to = $originalSource;

                $week->children[] = $linkedClone;
            }
        }

        $week->linked_to = $this->linkedTo ?: null;

        return WeekLinkedEvent::from([
            'week' => $week,
            'oldLinkedTo' => $oldLinkedTo,
            'newLinkedTo' => $week->linked_to,
        ]);
    }
}
