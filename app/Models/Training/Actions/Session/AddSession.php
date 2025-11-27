<?php

namespace App\Models\Training\Actions\Session;

use App\Models\Training\Data\SessionData;
use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingTree;

class AddSession extends Action
{
    public function __construct(
        public string $parentId,
        public int $day,
        public int $slot,
        public ?int $category = null,
        public array $exercises = [],
        public ?string $name = null,
        public ?string $id = null,
        public ?string $linkId = null,
    ) {}

    public function execute(TrainingTree $tree): SessionAddedEvent
    {
        $parent = $tree->getNode($this->parentId);

        if (!$parent) {
            throw new \Exception("Parent node not found");
        }

        if ($this->linkId) {
            $sourceNode = $tree->getNode($this->linkId);
            if (!$sourceNode) {
                throw new \Exception("Source node for linking not found");
            }

            $linkedNode = $sourceNode->createLinkedClone($parent->uuid, count($parent->children));
            $linkedNode->data->day = $this->day;
            $linkedNode->data->slot = $this->slot;

            $tree->addChild($parent, $linkedNode);

            return SessionAddedEvent::from(['parent' => $parent, 'child' => $linkedNode]);
        }

        $sessionData = new SessionData(
            name: $this->name,
            day: $this->day,
            slot: $this->slot,
            category: $this->category,
            exercises: $this->exercises
        );

        $node = $tree->addChild($parent, $sessionData);
        return SessionAddedEvent::from(['parent' => $parent, 'child' => $node]);
    }

    public static function fromParentId(string $parentId, int $day, int $slot, int $category, array $exercises = [], ?string $name = null): self
    {
        return new self(
            parentId: $parentId,
            day: $day,
            slot: $slot,
            category: $category,
            exercises: $exercises,
            name: $name
        );
    }
}
