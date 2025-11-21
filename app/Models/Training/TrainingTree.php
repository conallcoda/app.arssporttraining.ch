<?php

namespace App\Models\Training;

use App\Data\AbstractData;

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
use App\Models\Training\Data\TrainingData;

class TrainingTree extends AbstractData
{
    public array $actions = [
        'block' => [
            'add' => AddBlock::class,
            'delete' => DeleteNode::class,
            'duplicate' => DuplicateNode::class,
            'move' => MoveNode::class,
        ],
        'week' => [
            'add' => AddWeek::class,
            'delete' => DeleteNode::class,
            'duplicate' => DuplicateNode::class,
            'move' => MoveNode::class,
        ],
        'session' => [
            'add' => AddSession::class,
            'update' => UpdateSession::class,
            'move' => MoveSession::class,
            'swap' => SwapSessions::class,
            'delete' => DeleteSession::class,
        ],
    ];

    public ?TrainingNode $originalRoot = null;

    public function __construct(
        public TrainingNode $root,
        public array $deletedNodes = [],
        public int $lastChangeTimestamp = 0,
        public array $events = []
    ) {
        if ($this->lastChangeTimestamp === 0) {
            $this->lastChangeTimestamp = time();
        }
        $this->originalRoot = $this->root ? self::deepCloneNode($this->root) : null;
    }

    public function executeAction(string $actionPath, array $params = []): mixed
    {
        [$nodeType, $actionName] = explode('.', $actionPath);

        if (!isset($this->actions[$nodeType][$actionName])) {
            throw new \Exception("Action {$actionPath} not found");
        }

        $actionClass = $this->actions[$nodeType][$actionName];
        $action = $actionClass::from($params);

        $event = $action->execute($this);
        $this->events[] = $event;
        $this->markChanged();

        return $event;
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

    public function markChanged(): void
    {
        $this->lastChangeTimestamp = time();
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function clearEvents(): void
    {
        $this->events = [];
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
