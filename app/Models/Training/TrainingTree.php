<?php

namespace App\Models\Training;

use App\Data\AbstractData;
use App\Models\Training\Data\BlockData;
use App\Models\Training\Data\SessionData;
use App\Models\Training\Data\TrainingData;
use App\Models\Training\Data\WeekData;

use App\Models\Training\Actions\Season\AddBlock;


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

    protected function findParentNode(string $childUuid): ?TrainingNode
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
        $this->addChild($blockUuid, new WeekData());
    }

    public function deletePeriod(string $uuid): void
    {
        $this->deletedNodes[] = $uuid;

        $parentNode = $this->findParentNode($uuid);
        if ($parentNode) {
            $this->removeChild($parentNode->uuid, $uuid);
        } else {
            $this->markChanged();
        }
    }

    public function duplicatePeriod(string $uuid): void
    {
        $parentNode = $this->findParentNode($uuid);
        if (!$parentNode) {
            return;
        }

        foreach ($parentNode->children as $index => $child) {
            if ($child->uuid === $uuid) {
                $duplicate = self::deepCloneNode($child, generateNewUuid: true);
                array_splice($parentNode->children, $index + 1, 0, [$duplicate]);
                $this->renumberChildren($parentNode->children);
                break;
            }
        }

        $this->markChanged();
    }

    public function moveUp(string $uuid): void
    {
        $this->moveNode($uuid, -1);
        $this->markChanged();
    }

    public function moveDown(string $uuid): void
    {
        $this->moveNode($uuid, 1);
        $this->markChanged();
    }

    protected function moveNode(string $uuid, int $direction): void
    {
        $parentNode = $this->findParentNode($uuid);
        if (!$parentNode || empty($parentNode->children)) {
            return;
        }

        foreach ($parentNode->children as $index => $child) {
            if ($child->uuid === $uuid) {
                $targetIndex = $index + $direction;

                if ($targetIndex >= 0 && $targetIndex < count($parentNode->children)) {
                    $currentNode = $parentNode->children[$index];
                    $targetNode = $parentNode->children[$targetIndex];

                    $tempSequence = $currentNode->sequence;
                    $currentNode->sequence = $targetNode->sequence;
                    $targetNode->sequence = $tempSequence;

                    $parentNode->children[$index] = $targetNode;
                    $parentNode->children[$targetIndex] = $currentNode;
                }
                break;
            }
        }
    }

    protected function renumberChildren(array &$children): void
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

        $sessionData = new SessionData(
            day: $day,
            slot: $slot,
            category: $category,
            exercises: $exercises
        );

        $this->addChild($weekUuid, $sessionData);
    }

    public function updateSession(string $weekUuid, string $sessionUuid, int $category, array $exercises = []): void
    {
        $this->updateNodeData($sessionUuid, function ($session) use ($category, $exercises) {
            $session->data->category = $category;
            $session->data->exercises = $exercises;
        });
    }

    public function moveSession(string $weekUuid, string $sessionUuid, int $newDay, int $newSlot): void
    {

        $this->updateNodeData($sessionUuid, function ($session) use ($newDay, $newSlot) {
            $session->data->day = $newDay;
            $session->data->slot = $newSlot;
        });
    }

    public function swapSessions(string $weekUuid, string $sessionUuid1, string $sessionUuid2): void
    {
        $session1 = $this->getNode($sessionUuid1);
        $session2 = $this->getNode($sessionUuid2);

        if ($session1 && $session2) {
            $tempDay = $session1->data->day;
            $tempSlot = $session1->data->slot;
            $newDay = $session2->data->day;
            $newSlot = $session2->data->slot;

            $this->updateNodeData($sessionUuid1, function ($session) use ($newDay, $newSlot) {
                $session->data->day = $newDay;
                $session->data->slot = $newSlot;
            });

            $this->updateNodeData($sessionUuid2, function ($session) use ($tempDay, $tempSlot) {
                $session->data->day = $tempDay;
                $session->data->slot = $tempSlot;
            });
        }
    }

    public function deleteSession(string $weekUuid, string $sessionUuid): void
    {
        $session = $this->getNode($sessionUuid);
        if ($session && $session->id) {
            $this->deletedNodes[] = $sessionUuid;
        }

        $this->removeChild($weekUuid, $sessionUuid);
    }


    protected function markChanged(): void
    {
        $this->lastChangeTimestamp = time();
    }

    protected static function deepCloneNode(TrainingNode $node, bool $generateNewUuid = false): TrainingNode
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
