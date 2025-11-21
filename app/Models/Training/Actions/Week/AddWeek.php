<?php

namespace App\Models\Training\Actions\Week;

use App\Models\Training\Data\WeekData;
use App\Models\Training\Actions\Action;
use App\Models\Training\TrainingTree;

class AddWeek extends Action
{
    public function __construct(
        public string $parentId,
        public ?string $id = null,
    ) {}

    public function execute(TrainingTree $tree): WeekAddedEvent
    {
        $parent = $tree->getNode($this->parentId);

        if (!$parent) {
            throw new \Exception("Parent node not found");
        }

        $node = $tree->addChild($parent, new WeekData());
        return WeekAddedEvent::from(['parent' => $parent, 'child' => $node]);
    }

    public static function fromParentId(string $parentId): self
    {
        return new self(parentId: $parentId);
    }
}
