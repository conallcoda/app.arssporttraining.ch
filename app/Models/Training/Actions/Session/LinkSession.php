<?php

namespace App\Models\Training\Actions\Session;

use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingTree;

class LinkSession extends Action
{
    public function __construct(
        public string $sessionId,
        public ?string $linkedTo = null,
    ) {}

    public function execute(TrainingTree $tree): SessionLinkedEvent
    {
        $session = $tree->getNode($this->sessionId);

        if (! $session) {
            throw new \Exception('Session node not found');
        }

        $oldLinkedTo = $session->linked_to;
        $isUnlinking = $oldLinkedTo !== null && ($this->linkedTo === null || $this->linkedTo === '');

        if ($isUnlinking) {
            $sourceSession = $tree->getNode($oldLinkedTo);
            if ($sourceSession) {
                $sourceData = $sourceSession->getData();
                $session->data->name = $sourceData->name;
                $session->data->color = $sourceData->color;
                $session->data->exercises = $sourceData->exercises;
            }
        }

        $session->linked_to = $this->linkedTo ?: null;

        return SessionLinkedEvent::from([
            'session' => $session,
            'oldLinkedTo' => $oldLinkedTo,
            'newLinkedTo' => $session->linked_to,
        ]);
    }
}
