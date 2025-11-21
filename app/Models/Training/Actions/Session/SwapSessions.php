<?php

namespace App\Models\Training\Actions\Session;

use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingTree;

class SwapSessions extends Action
{
    public function __construct(
        public string $session1Id,
        public string $session2Id,
    ) {}

    public function execute(TrainingTree $tree): SessionsSwappedEvent
    {
        $session1 = $tree->getNode($this->session1Id);
        $session2 = $tree->getNode($this->session2Id);

        if (!$session1 || !$session2) {
            throw new \Exception("One or both session nodes not found");
        }

        $temp1Day = $session1->data->day;
        $temp1Slot = $session1->data->slot;
        $temp2Day = $session2->data->day;
        $temp2Slot = $session2->data->slot;

        $session1->data->day = $temp2Day;
        $session1->data->slot = $temp2Slot;
        $session2->data->day = $temp1Day;
        $session2->data->slot = $temp1Slot;

        return SessionsSwappedEvent::from([
            'session1' => $session1,
            'session2' => $session2
        ]);
    }

    public static function fromSessionIds(string $session1Id, string $session2Id): self
    {
        return new self(
            session1Id: $session1Id,
            session2Id: $session2Id
        );
    }
}
