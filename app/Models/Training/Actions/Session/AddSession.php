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
        public int $category,
        public array $exercises = [],
        public ?string $id = null,
    ) {}

    public function execute(TrainingTree $tree): SessionAddedEvent
    {
        $parent = $tree->getNode($this->parentId);

        if (!$parent) {
            throw new \Exception("Parent node not found");
        }

        $sessionData = new SessionData(
            day: $this->day,
            slot: $this->slot,
            category: $this->category,
            exercises: $this->exercises
        );

        $node = $tree->addChild($parent, $sessionData);
        return SessionAddedEvent::from(['parent' => $parent, 'child' => $node]);
    }

    public static function fromParentId(string $parentId, int $day, int $slot, int $category, array $exercises = []): self
    {
        return new self(
            parentId: $parentId,
            day: $day,
            slot: $slot,
            category: $category,
            exercises: $exercises
        );
    }
}
