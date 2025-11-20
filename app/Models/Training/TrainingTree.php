<?php

namespace App\Models\Training;

use App\Data\AbstractData;
use App\Models\Training\Data\BlockData;
use App\Models\Training\Data\SessionData;
use App\Models\Training\Data\WeekData;
use App\Models\Training\Data\TrainingData;

class TrainingTree extends AbstractData
{

    public ?TrainingNode $originalRoot = null;

    public array $nodeRegistry = [];

    public function __construct(
        public ?TrainingNode $root = null,
        public array $deletedNodes = [],
        public int $lastChangeTimestamp = 0
    ) {
        if ($this->lastChangeTimestamp === 0) {
            $this->lastChangeTimestamp = time();
        }

        $this->originalRoot = $this->root ? self::deepCloneNode($this->root) : null;
        $this->rebuildRegistry();
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
        $this->rebuildRegistry();
    }

    public function revert(): void
    {
        $this->deletedNodes = [];
        $this->root = $this->originalRoot ? self::deepCloneNode($this->originalRoot) : null;
        $this->markChanged();
        $this->rebuildRegistry();
    }


    protected function rebuildRegistry(): void
    {
        $this->nodeRegistry = [];
        if ($this->root) {
            $this->addNodeToRegistry($this->root);
        }
    }

    protected function addNodeToRegistry(TrainingNode $node): void
    {
        $this->nodeRegistry[$node->uuid] = $node;
        foreach ($node->children as $child) {
            $this->addNodeToRegistry($child);
        }
    }

    public function getNode(string $uuid): ?TrainingNode
    {
        return $this->nodeRegistry[$uuid] ?? null;
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
        if ($this->root && $this->root->uuid === $rootUuid) {
            $newSequence = count($this->root->children);
            $blockData = new BlockData();

            $newBlock = TrainingNode::fromData(
                data: $blockData,
                sequence: $newSequence,
                parentUuid: null
            );

            $this->root->children[] = $newBlock;
            $this->markChanged();
        }
    }

    public function addWeek(string $blockUuid): void
    {
        $parentNode = $this->getNode($blockUuid);
        if ($parentNode) {
            $newSequence = count($parentNode->children);
            $newChild = TrainingNode::fromData(
                data: new WeekData(),
                sequence: $newSequence,
                parentUuid: null
            );
            $parentNode->children[] = $newChild;
        }
        $this->markChanged();
    }

    public function deletePeriod(string $uuid): void
    {
        $this->deletedNodes[] = $uuid;

        $parentNode = $this->findParentNode($uuid);
        if ($parentNode) {
            foreach ($parentNode->children as $index => $child) {
                if ($child->uuid === $uuid) {
                    array_splice($parentNode->children, $index, 1);
                    $this->renumberChildren($parentNode->children);
                    break;
                }
            }
        }

        $this->markChanged();
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

    public function addSession(string $weekUuid, int $day, int $slot, int $category, array $exercises = []): void
    {
        $weekNode = $this->getNode($weekUuid);

        if (!$weekNode) {
            logger()->info('addSession: Week node not found', ['weekUuid' => $weekUuid]);
            return;
        }

        foreach ($weekNode->children as $index => $session) {
            if ($session->data->day === $day && $session->data->slot === $slot) {
                if ($session->id) {
                    $this->deletedNodes[] = $session->uuid;
                }
                array_splice($weekNode->children, $index, 1);
                break;
            }
        }

        $sessionData = new SessionData(
            day: $day,
            slot: $slot,
            category: $category,
            exercises: $exercises
        );

        $newSession = TrainingNode::fromData(
            data: $sessionData,
            sequence: count($weekNode->children),
            parentUuid: $weekNode->uuid
        );

        $weekNode->children[] = $newSession;
        logger()->info('addSession: Session added', [
            'weekUuid' => $weekUuid,
            'sessionUuid' => $newSession->uuid,
            'childrenCount' => count($weekNode->children),
            'day' => $day,
            'slot' => $slot
        ]);
        $this->markChanged();
    }

    public function updateSession(string $weekUuid, string $sessionUuid, int $category, array $exercises = []): void
    {
        $session = $this->getNode($sessionUuid);
        if ($session) {
            $session->data->category = $category;
            $session->data->exercises = $exercises;
        }
        $this->markChanged();
    }

    public function moveSession(string $weekUuid, string $sessionUuid, int $newDay, int $newSlot): void
    {
        $weekNode = $this->getNode($weekUuid);
        if (!$weekNode) {
            return;
        }

        foreach ($weekNode->children as $index => $existingSession) {
            if ($existingSession->data->day === $newDay && $existingSession->data->slot === $newSlot && $existingSession->uuid !== $sessionUuid) {
                if ($existingSession->id) {
                    $this->deletedNodes[] = $existingSession->uuid;
                }
                array_splice($weekNode->children, $index, 1);
                break;
            }
        }

        $session = $this->getNode($sessionUuid);
        if ($session) {
            $session->data->day = $newDay;
            $session->data->slot = $newSlot;
        }

        $this->markChanged();
    }

    public function swapSessions(string $weekUuid, string $sessionUuid1, string $sessionUuid2): void
    {
        $session1 = $this->getNode($sessionUuid1);
        $session2 = $this->getNode($sessionUuid2);

        if ($session1 && $session2) {
            $tempDay = $session1->data->day;
            $tempSlot = $session1->data->slot;

            $session1->data->day = $session2->data->day;
            $session1->data->slot = $session2->data->slot;

            $session2->data->day = $tempDay;
            $session2->data->slot = $tempSlot;
        }

        $this->markChanged();
    }

    public function deleteSession(string $weekUuid, string $sessionUuid): void
    {
        $session = $this->getNode($sessionUuid);
        if ($session && $session->id) {
            $this->deletedNodes[] = $sessionUuid;
        }

        $weekNode = $this->getNode($weekUuid);
        if ($weekNode) {
            foreach ($weekNode->children as $index => $session) {
                if ($session->uuid === $sessionUuid) {
                    array_splice($weekNode->children, $index, 1);
                    break;
                }
            }
        }

        $this->markChanged();
    }


    public function buildFlatList(TrainingNode $node, array &$flat): void
    {
        $flat[$node->uuid] = $node;
        foreach ($node->children as $child) {
            $this->buildFlatList($child, $flat);
        }
    }

    protected function markChanged(): void
    {
        $this->lastChangeTimestamp = time();
        $this->rebuildRegistry();
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
            children: $clonedChildren
        );

        return $cloned;
    }
}
