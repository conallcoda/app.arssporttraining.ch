<?php

namespace App\Models\Training\Actions\Session;

use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingNode;
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

        $this->deleteLinkedSessions($tree, $this->sessionId);

        if ($session->id) {
            $tree->deletedNodes[] = $this->sessionId;
        }

        $tree->removeChild($parent, $this->sessionId);

        return SessionDeletedEvent::from([
            'parent' => $parent,
            'session' => $session
        ]);
    }

    protected function deleteLinkedSessions(TrainingTree $tree, string $sourceUuid): void
    {
        $linkedSessions = $this->findLinkedSessions($tree->root, $sourceUuid);

        foreach ($linkedSessions as $linkedSession) {
            $linkedParent = $tree->findParentNode($linkedSession->uuid);
            if ($linkedParent) {
                if ($linkedSession->id) {
                    $tree->deletedNodes[] = $linkedSession->uuid;
                }
                $tree->removeChild($linkedParent, $linkedSession->uuid);
            }
        }
    }

    protected function findLinkedSessions(TrainingNode $node, string $sourceUuid): array
    {
        $linked = [];

        if ($node->type === 'session' && $node->linked_to === $sourceUuid) {
            $linked[] = $node;
        }

        foreach ($node->children as $child) {
            $linked = array_merge($linked, $this->findLinkedSessions($child, $sourceUuid));
        }

        return $linked;
    }

    public static function fromIds(string $parentId, string $sessionId): self
    {
        return new self(
            parentId: $parentId,
            sessionId: $sessionId
        );
    }
}
