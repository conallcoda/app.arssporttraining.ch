<?php

namespace App\Models\Training;

use App\Data\AbstractData;
use App\Models\Training\Data\BlockData;
use App\Models\Training\Data\SessionData;
use App\Models\Training\Data\TrainingData;
use App\Models\Training\Data\WeekData;

use App\Models\Training\Actions\Block\AddBlock;
use App\Models\Training\Actions\Week\AddWeek;
use App\Models\Training\Actions\Session\AddSession;
use App\Models\Training\Actions\Session\UpdateSession;
use App\Models\Training\Actions\Session\MoveSession;
use App\Models\Training\Actions\Session\SwapSessions;
use App\Models\Training\Actions\Session\DeleteSession;
use App\Models\Training\Actions\Node\DeleteNode;
use App\Models\Training\Actions\Node\DuplicateNode;
use App\Models\Training\Actions\Node\MoveNode;


class TrainingTree extends AbstractData
{
    public ?TrainingNode $originalRoot = null;
    public array $registry = [];

    public function __construct(
        public TrainingNode $root,
        public array $deletedNodes = [],
        public int $lastChangeTimestamp = 0
    ) {
        if ($this->lastChangeTimestamp === 0) {
            $this->lastChangeTimestamp = time();
        }
        $this->originalRoot = $this->root ? self::deepCloneNode($this->root) : null;
    }

    public function getNode(string|TrainingNode $uuid): ?TrainingNode
    {
        if ($uuid instanceof TrainingNode) {
            return $uuid;
        }
        return $this->searchForNode($this->root, $uuid);
    }

    protected function searchForNode(?TrainingNode $node, string $uuid): ?TrainingNode
    {
        if (!$node) {
            return null;
        }

        if ($node->uuid === $uuid) {
            return $node;
        }

        foreach ($node->children as $child) {
            $found = $this->searchForNode($child, $uuid);
            if ($found) {
                return $found;
            }
        }
        return null;
    }

    public static function fromTrainingNode(TrainingNode $root): static
    {
        return new self(
            root: $root
        );
    }

    public function hasChanges(): bool
    {
        if (!empty($this->deletedNodes)) {
            return true;
        }

        return $this->treeHasChanges($this->root, $this->originalRoot);
    }

    protected function treeHasChanges(?TrainingNode $current, ?TrainingNode $original): bool
    {
        if (!$current && !$original) {
            return false;
        }

        if (!$current || !$original) {
            return true;
        }

        if ($current->sequence !== $original->sequence) {
            return true;
        }

        if ($current->data->toArray() !== $original->data->toArray()) {
            return true;
        }

        if (count($current->children) !== count($original->children)) {
            return true;
        }

        $currentUuids = array_map(fn($child) => $child->uuid, $current->children);
        $originalUuids = array_map(fn($child) => $child->uuid, $original->children);

        if ($currentUuids !== $originalUuids) {
            return true;
        }

        foreach ($current->children as $index => $child) {
            if ($this->treeHasChanges($child, $original->children[$index])) {
                return true;
            }
        }

        return false;
    }

    public function save(): void
    {
        if (!$this->hasChanges()) {
            return;
        }

        foreach ($this->deletedNodes as $uuid) {
            $period = TrainingPeriod::where('uuid', $uuid)->first();
            if ($period) {
                $period->delete();
            }
        }

        if ($this->root) {
            $this->root->save();
        }

        $this->deletedNodes = [];
    }

    public function revert(): void
    {
        $this->deletedNodes = [];
        $this->root = $this->originalRoot ? self::deepCloneNode($this->originalRoot) : null;
        $this->markChanged();
    }

    public function findParentNode(string $childUuid): ?TrainingNode
    {
        if (!$this->root) {
            return null;
        }

        return $this->searchForParent($this->root, $childUuid);
    }

    protected function searchForParent(TrainingNode $node, string $childUuid): ?TrainingNode
    {
        foreach ($node->children as $child) {
            if ($child->uuid === $childUuid) {
                return $node;
            }
            $found = $this->searchForParent($child, $childUuid);
            if ($found) {
                return $found;
            }
        }
        return null;
    }

    public function addBlock(string $rootUuid): void
    {
        $event = AddBlock::from($rootUuid)->execute($this);
    }

    public function addWeek(string $blockUuid): void
    {
        $event = AddWeek::fromParentId($blockUuid)->execute($this);
    }

    public function deletePeriod(string $uuid): void
    {
        $event = DeleteNode::fromNodeId($uuid)->execute($this);
        $this->markChanged();
    }

    public function duplicatePeriod(string $uuid): void
    {
        $event = DuplicateNode::fromNodeId($uuid)->execute($this);
        $this->markChanged();
    }

    public function moveUp(string $uuid): void
    {
        $event = MoveNode::fromNodeId($uuid, -1)->execute($this);
        $this->markChanged();
    }

    public function moveDown(string $uuid): void
    {
        $event = MoveNode::fromNodeId($uuid, 1)->execute($this);
        $this->markChanged();
    }

    public function renumberChildren(array &$children): void
    {
        foreach ($children as $index => $child) {
            $child->sequence = $index;
        }
    }

    public function addChild(string|TrainingNode $parent, TrainingData|TrainingNode $data): ?TrainingNode
    {
        $parentNode = $this->getNode($parent);
        if (!$parentNode) {
            return null;
        }

        $newSequence = count($parentNode->children);

        $newChild = $data instanceof TrainingNode
            ? $data
            : TrainingNode::fromData(
                data: $data,
                sequence: $newSequence,
                parentUuid: $parentNode->uuid
            );

        $parentNode->children[] = $newChild;
        $this->markChanged();

        return $newChild;
    }

    public function removeChild(string|TrainingNode $parent, string $childUuid): bool
    {
        $parentNode = $this->getNode($parent);
        if (!$parentNode) {
            return false;
        }

        foreach ($parentNode->children as $index => $child) {
            if ($child->uuid === $childUuid) {
                array_splice($parentNode->children, $index, 1);
                $this->renumberChildren($parentNode->children);
                $this->markChanged();
                return true;
            }
        }

        return false;
    }

    protected function updateNodeData(string $uuid, callable $updater): bool
    {
        $node = $this->getNode($uuid);
        if (!$node) {
            return false;
        }

        $updater($node);
        $this->markChanged();
        return true;
    }

    public function addSession(string $weekUuid, int $day, int $slot, int $category, array $exercises = []): void
    {
        $event = AddSession::fromParentId($weekUuid, $day, $slot, $category, $exercises)->execute($this);
    }

    public function updateSession(string $weekUuid, string $sessionUuid, int $category, array $exercises = []): void
    {
        $event = UpdateSession::fromSessionId($sessionUuid, $category, $exercises)->execute($this);
        $this->markChanged();
    }

    public function moveSession(string $weekUuid, string $sessionUuid, int $newDay, int $newSlot): void
    {
        $event = MoveSession::fromSessionId($sessionUuid, $newDay, $newSlot)->execute($this);
        $this->markChanged();
    }

    public function swapSessions(string $weekUuid, string $sessionUuid1, string $sessionUuid2): void
    {
        $event = SwapSessions::fromSessionIds($sessionUuid1, $sessionUuid2)->execute($this);
        $this->markChanged();
    }

    public function deleteSession(string $weekUuid, string $sessionUuid): void
    {
        $event = DeleteSession::fromIds($weekUuid, $sessionUuid)->execute($this);
        $this->markChanged();
    }


    public function markChanged(): void
    {
        $this->lastChangeTimestamp = time();
    }

    public static function deepCloneNode(TrainingNode $node, bool $generateNewUuid = false): TrainingNode
    {
        $clonedChildren = [];
        foreach ($node->children as $child) {
            $clonedChildren[] = self::deepCloneNode($child, $generateNewUuid);
        }

        $clonedData = $node->data::from($node->data->toArray());

        $cloned = new TrainingNode(
            uuid: $generateNewUuid ? TrainingPeriod::createUuid() : $node->uuid,
            id: $generateNewUuid ? null : $node->id,
            parent: $node->parent,
            name: $node->name,
            sequence: $node->sequence,
            type: $node->type,
            data: $clonedData,
            children: $clonedChildren,
            path: $node->path
        );

        return $cloned;
    }
}
