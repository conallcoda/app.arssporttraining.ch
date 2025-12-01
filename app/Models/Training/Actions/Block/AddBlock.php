<?php

namespace App\Models\Training\Actions\Block;

use App\Models\Training\Actions\Action;
use App\Models\Training\Data\BlockData;
use App\Models\Training\TrainingTree;

class AddBlock extends Action
{
    public function __construct(
        public string $parentId,
        public ?string $id = null,
    ) {}

    public function execute(TrainingTree $tree): BlockAddedEvent
    {
        if ($this->parentId !== $tree->root->uuid) {
            throw new \Exception('Parent node not found');
        }

        $parent = $tree->getNode($this->parentId);
        $node = $tree->addChild($parent, new BlockData);

        return BlockAddedEvent::from(['parent' => $parent, 'child' => $node]);
    }

    public static function fromParentId(string $parentId): self
    {
        return new self(parentId: $parentId);
    }
}
