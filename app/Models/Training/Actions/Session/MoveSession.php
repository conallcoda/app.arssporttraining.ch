<?php

namespace App\Models\Training\Actions\Session;

use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingTree;

class MoveSession extends Action
{
    public function __construct(
        public string $sessionId,
        public int $newDay,
        public int $newSlot,
    ) {}

    public function execute(TrainingTree $tree): SessionMovedEvent
    {
        $session = $tree->getNode($this->sessionId);

        if (!$session) {
            throw new \Exception("Session node not found");
        }

        $parent = $tree->findParentNode($this->sessionId);

        $oldDay = $session->data->day;
        $oldSlot = $session->data->slot;

        $session->data->day = $this->newDay;
        $session->data->slot = $this->newSlot;

        return SessionMovedEvent::from([
            'parent' => $parent,
            'session' => $session,
            'oldDay' => $oldDay,
            'oldSlot' => $oldSlot,
            'newDay' => $this->newDay,
            'newSlot' => $this->newSlot
        ]);
    }

    public static function fromSessionId(string $sessionId, int $newDay, int $newSlot): self
    {
        return new self(
            sessionId: $sessionId,
            newDay: $newDay,
            newSlot: $newSlot
        );
    }
}
