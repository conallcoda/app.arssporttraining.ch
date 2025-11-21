<?php

namespace App\Models\Training\Actions\Session;

use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingTree;

class DeleteSession extends Action
{
    public function __construct(
        public string $parentId,
        public string $sessionId,
    ) {}

    public function execute(TrainingTree $tree): SessionDeletedEvent
    {
        $parent = $tree->getNode($this->parentId);
        $session = $tree->getNode($this->sessionId);

        if (!$parent || !$session) {
            throw new \Exception("Parent or session node not found");
        }

        if ($session->id) {
            $tree->deletedNodes[] = $this->sessionId;
        }

        $tree->removeChild($parent, $this->sessionId);

        return SessionDeletedEvent::from([
            'parent' => $parent,
            'session' => $session
        ]);
    }

    public static function fromIds(string $parentId, string $sessionId): self
    {
        return new self(
            parentId: $parentId,
            sessionId: $sessionId
        );
    }
}
