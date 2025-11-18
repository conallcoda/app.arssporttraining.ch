<?php

namespace App\Livewire;

use App\Models\Training\TrainingPeriod;
use App\Models\Training\TrainingNode;
use App\Models\Training\Data\BlockData;
use App\Models\Training\Data\WeekData;
use App\Models\Training\Data\SessionData;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;


class TrainingPlanner extends Component
{
    public $expanded = [];
    public $maxDepth = 2;

    #[Url(as: 'period')]
    public $selectedPeriodUuid = null;

    public ?TrainingNode $season = null;
    public ?TrainingNode $originalSeason = null;
    public $deletedNodes = [];
    public int $lastChangeTimestamp;
    public array $history = [];
    public int $historyIndex = -1;

    public function mount($maxDepth = 2)
    {
        $this->maxDepth = $maxDepth;
        $this->lastChangeTimestamp = time();

        $seasonModel = TrainingPeriod::where('type', 'season')->first();

        if ($seasonModel) {
            $tree = TrainingPeriod::withMaxDepth(PHP_INT_MAX, function() use ($seasonModel) {
                return $seasonModel->descendantsAndSelf()
                    ->orderBy('sequence')
                    ->get()
                    ->toTree();
            });

            if ($tree->isNotEmpty()) {
                $seasonModel = $tree->first();
                $this->expandInitialNodes($seasonModel, 0);

                $this->season = TrainingNode::fromModel($seasonModel);
                $this->originalSeason = clone $this->season;

                $this->history = [$this->deepCloneTree($this->season)];
                $this->historyIndex = 0;

                if (!$this->selectedPeriodUuid && !empty($this->season->children)) {
                    $firstBlock = $this->season->children[0];
                    if (!empty($firstBlock->children)) {
                        $firstWeek = $firstBlock->children[0];
                        $this->selectedPeriodUuid = $firstWeek->uuid;
                    }
                }
            }
        }
    }

    protected function expandInitialNodes($model, $depth)
    {
        if ($depth < $this->maxDepth) {
            $this->expanded[$model->id] = true;

            if ($depth + 1 < $this->maxDepth) {
                foreach ($model->children as $child) {
                    $this->expandInitialNodes($child, $depth + 1);
                }
            }
        }
    }

    public function toggle($nodeId)
    {
        if (isset($this->expanded[$nodeId])) {
            unset($this->expanded[$nodeId]);
        } else {
            $this->expanded[$nodeId] = true;
        }
    }

    public function selectPeriod($uuid)
    {
        $this->selectedPeriodUuid = $uuid;
    }

    #[Computed]
    public function hasChanges(): bool
    {
        if (!empty($this->deletedNodes)) {
            return true;
        }

        return $this->treeHasChanges($this->season, $this->originalSeason);
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

    public function saveChanges()
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

        if ($this->season) {
            $this->season->save();
        }

        $this->deletedNodes = [];
        $this->mount($this->maxDepth);
    }

    public function revertChanges()
    {
        $this->deletedNodes = [];
        $this->mount($this->maxDepth);
    }

    public function addBlock($seasonUuid)
    {
        if ($this->season && $this->season->uuid === $seasonUuid) {
            $newSequence = count($this->season->children);
            $blockData = new BlockData();

            $newBlock = TrainingNode::fromData(
                data: $blockData,
                sequence: $newSequence,
                parentUuid: null
            );

            $this->season->children[] = $newBlock;
            $this->markChanged();
        }
    }

    public function addWeek($blockUuid)
    {
        $wasEmpty = $this->isNodeEmpty($this->season, $blockUuid);
        $this->addChildToNode($this->season, $blockUuid, new WeekData());

        if ($wasEmpty) {
            $nodeIdentifier = $this->getNodeIdentifier($this->season, $blockUuid);
            if ($nodeIdentifier) {
                $this->expanded[$nodeIdentifier] = true;
            }
        }

        $this->markChanged();
    }

    protected function isNodeEmpty(?TrainingNode $node, string $uuid): bool
    {
        if (!$node) {
            return false;
        }

        if ($node->uuid === $uuid) {
            return empty($node->children);
        }

        foreach ($node->children as $child) {
            $result = $this->isNodeEmpty($child, $uuid);
            if ($result !== false || $child->uuid === $uuid) {
                return $result;
            }
        }

        return false;
    }

    protected function getNodeIdentifier(?TrainingNode $node, string $uuid): ?string
    {
        if (!$node) {
            return null;
        }

        if ($node->uuid === $uuid) {
            return $node->id ? (string)$node->id : 'temp-' . spl_object_id($node);
        }

        foreach ($node->children as $child) {
            $identifier = $this->getNodeIdentifier($child, $uuid);
            if ($identifier !== null) {
                return $identifier;
            }
        }

        return null;
    }

    protected function addChildToNode(?TrainingNode $node, string $parentUuid, $data): bool
    {
        if (!$node) {
            return false;
        }

        if ($node->uuid === $parentUuid) {
            $newSequence = count($node->children);
            $newChild = TrainingNode::fromData(
                data: $data,
                sequence: $newSequence,
                parentUuid: null
            );

            $node->children[] = $newChild;
            return true;
        }

        foreach ($node->children as $child) {
            if ($this->addChildToNode($child, $parentUuid, $data)) {
                return true;
            }
        }

        return false;
    }

    public function deletePeriod($uuid)
    {
        if ($this->selectedPeriodUuid === $uuid) {
            $this->selectedPeriodUuid = null;
        }

        $this->deletedNodes[] = $uuid;
        $this->removeNodeFromTree($this->season, $uuid);
        $this->markChanged();
    }

    public function duplicatePeriod($uuid)
    {
        $this->duplicateNodeInTree($this->season, $uuid);
        $this->markChanged();
    }

    protected function duplicateNodeInTree(?TrainingNode $node, string $uuid): bool
    {
        if (!$node || empty($node->children)) {
            return false;
        }

        foreach ($node->children as $index => $child) {
            if ($child->uuid === $uuid) {
                $duplicate = $this->deepCloneNode($child, generateNewUuid: true);

                array_splice($node->children, $index + 1, 0, [$duplicate]);
                $this->renumberChildren($node->children);

                return true;
            }

            if ($this->duplicateNodeInTree($child, $uuid)) {
                return true;
            }
        }

        return false;
    }

    protected function deepCloneNode(TrainingNode $node, bool $generateNewUuid = false): TrainingNode
    {
        $clonedChildren = [];
        foreach ($node->children as $child) {
            $clonedChildren[] = $this->deepCloneNode($child, $generateNewUuid);
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

    protected function deepCloneTree(?TrainingNode $node): ?TrainingNode
    {
        if (!$node) {
            return null;
        }

        return $this->deepCloneNode($node);
    }

    protected function renumberChildren(array &$children): void
    {
        foreach ($children as $index => $child) {
            $child->sequence = $index;
        }
    }

    public function moveUp($uuid)
    {
        $this->moveNode($uuid, -1);
        $this->markChanged();
    }

    public function moveDown($uuid)
    {
        $this->moveNode($uuid, 1);
        $this->markChanged();
    }

    protected function moveNode(string $uuid, int $direction): void
    {
        $this->swapNodeInTree($this->season, $uuid, $direction);
    }

    protected function swapNodeInTree(?TrainingNode $node, string $uuid, int $direction): bool
    {
        if (!$node || empty($node->children)) {
            return false;
        }

        foreach ($node->children as $index => $child) {
            if ($child->uuid === $uuid) {
                $targetIndex = $index + $direction;

                if ($targetIndex >= 0 && $targetIndex < count($node->children)) {
                    $currentNode = $node->children[$index];
                    $targetNode = $node->children[$targetIndex];

                    $tempSequence = $currentNode->sequence;
                    $currentNode->sequence = $targetNode->sequence;
                    $targetNode->sequence = $tempSequence;

                    $node->children[$index] = $targetNode;
                    $node->children[$targetIndex] = $currentNode;

                    return true;
                }
                return false;
            }

            if ($this->swapNodeInTree($child, $uuid, $direction)) {
                return true;
            }
        }

        return false;
    }

    protected function removeNodeFromTree(?TrainingNode $node, string $uuid): bool
    {
        if (!$node || empty($node->children)) {
            return false;
        }

        foreach ($node->children as $index => $child) {
            if ($child->uuid === $uuid) {
                array_splice($node->children, $index, 1);
                $this->renumberChildren($node->children);
                return true;
            }

            if ($this->removeNodeFromTree($child, $uuid)) {
                return true;
            }
        }

        return false;
    }

    protected function buildFlatList(TrainingNode $node, array &$flat): void
    {
        $flat[$node->uuid] = $node;
        foreach ($node->children as $child) {
            $this->buildFlatList($child, $flat);
        }
    }

    protected function markChanged(): void
    {
        $this->lastChangeTimestamp = time();

        if ($this->historyIndex < count($this->history) - 1) {
            $this->history = array_slice($this->history, 0, $this->historyIndex + 1);
        }

        $this->history[] = $this->deepCloneTree($this->season);
        $this->historyIndex = count($this->history) - 1;
    }

    public function undo(): void
    {
        if ($this->historyIndex > 0) {
            $this->historyIndex--;
            $this->season = $this->deepCloneTree($this->history[$this->historyIndex]);
            $this->deletedNodes = [];
            $this->lastChangeTimestamp = time();
        }
    }

    public function redo(): void
    {
        if ($this->historyIndex < count($this->history) - 1) {
            $this->historyIndex++;
            $this->season = $this->deepCloneTree($this->history[$this->historyIndex]);
            $this->deletedNodes = [];
            $this->lastChangeTimestamp = time();
        }
    }

    #[Computed]
    public function canUndo(): bool
    {
        return $this->historyIndex > 0;
    }

    #[Computed]
    public function canRedo(): bool
    {
        return $this->historyIndex < count($this->history) - 1;
    }

    #[On('addSession')]
    public function addSession(string $weekUuid, int $day, int $slot, int $category)
    {
        $sessionData = new SessionData(
            day: $day,
            slot: $slot,
            category: $category
        );

        $this->addSessionToWeek($this->season, $weekUuid, $sessionData);
        $this->markChanged();
    }

    #[On('updateSession')]
    public function updateSession(string $weekUuid, string $sessionUuid, int $category)
    {
        $this->updateSessionInWeek($this->season, $weekUuid, $sessionUuid, $category);
        $this->markChanged();
    }

    #[On('moveSession')]
    public function moveSession(string $weekUuid, string $sessionUuid, int $newDay, int $newSlot)
    {
        $this->moveSessionInWeek($this->season, $weekUuid, $sessionUuid, $newDay, $newSlot);
        $this->markChanged();
    }

    #[On('swapSessions')]
    public function swapSessions(string $weekUuid, string $sessionUuid1, string $sessionUuid2)
    {
        $this->swapSessionsInWeek($this->season, $weekUuid, $sessionUuid1, $sessionUuid2);
        $this->markChanged();
    }

    #[On('deleteSession')]
    public function deleteSession(string $weekUuid, string $sessionUuid)
    {
        $session = $this->findSessionInTree($this->season, $sessionUuid);
        if ($session && $session->id) {
            $this->deletedNodes[] = $sessionUuid;
        }

        $this->deleteSessionFromWeek($this->season, $weekUuid, $sessionUuid);
        $this->markChanged();
    }

    protected function addSessionToWeek(?TrainingNode $node, string $weekUuid, SessionData $sessionData): bool
    {
        if (!$node) {
            return false;
        }

        if ($node->uuid === $weekUuid) {
            foreach ($node->children as $index => $session) {
                if ($session->data->day === $sessionData->day && $session->data->slot === $sessionData->slot) {
                    if ($session->id) {
                        $this->deletedNodes[] = $session->uuid;
                    }
                    array_splice($node->children, $index, 1);
                    break;
                }
            }

            $newSession = TrainingNode::fromData(
                data: $sessionData,
                sequence: count($node->children),
                parentUuid: $node->uuid
            );

            $node->children[] = $newSession;
            return true;
        }

        foreach ($node->children as $child) {
            if ($this->addSessionToWeek($child, $weekUuid, $sessionData)) {
                return true;
            }
        }

        return false;
    }

    protected function updateSessionInWeek(?TrainingNode $node, string $weekUuid, string $sessionUuid, int $category): bool
    {
        if (!$node) {
            return false;
        }

        if ($node->uuid === $weekUuid) {
            foreach ($node->children as $session) {
                if ($session->uuid === $sessionUuid) {
                    $session->data->category = $category;
                    return true;
                }
            }
        }

        foreach ($node->children as $child) {
            if ($this->updateSessionInWeek($child, $weekUuid, $sessionUuid, $category)) {
                return true;
            }
        }

        return false;
    }

    protected function moveSessionInWeek(?TrainingNode $node, string $weekUuid, string $sessionUuid, int $newDay, int $newSlot): bool
    {
        if (!$node) {
            return false;
        }

        if ($node->uuid === $weekUuid) {
            foreach ($node->children as $index => $existingSession) {
                if ($existingSession->data->day === $newDay && $existingSession->data->slot === $newSlot && $existingSession->uuid !== $sessionUuid) {
                    if ($existingSession->id) {
                        $this->deletedNodes[] = $existingSession->uuid;
                    }
                    array_splice($node->children, $index, 1);
                    break;
                }
            }

            foreach ($node->children as $session) {
                if ($session->uuid === $sessionUuid) {
                    $session->data->day = $newDay;
                    $session->data->slot = $newSlot;
                    return true;
                }
            }
        }

        foreach ($node->children as $child) {
            if ($this->moveSessionInWeek($child, $weekUuid, $sessionUuid, $newDay, $newSlot)) {
                return true;
            }
        }

        return false;
    }

    protected function swapSessionsInWeek(?TrainingNode $node, string $weekUuid, string $sessionUuid1, string $sessionUuid2): bool
    {
        if (!$node) {
            return false;
        }

        if ($node->uuid === $weekUuid) {
            $session1 = null;
            $session2 = null;

            foreach ($node->children as $session) {
                if ($session->uuid === $sessionUuid1) {
                    $session1 = $session;
                }
                if ($session->uuid === $sessionUuid2) {
                    $session2 = $session;
                }
            }

            if ($session1 && $session2) {
                $tempDay = $session1->data->day;
                $tempSlot = $session1->data->slot;

                $session1->data->day = $session2->data->day;
                $session1->data->slot = $session2->data->slot;

                $session2->data->day = $tempDay;
                $session2->data->slot = $tempSlot;

                return true;
            }
        }

        foreach ($node->children as $child) {
            if ($this->swapSessionsInWeek($child, $weekUuid, $sessionUuid1, $sessionUuid2)) {
                return true;
            }
        }

        return false;
    }

    protected function deleteSessionFromWeek(?TrainingNode $node, string $weekUuid, string $sessionUuid): bool
    {
        if (!$node) {
            return false;
        }

        if ($node->uuid === $weekUuid) {
            foreach ($node->children as $index => $session) {
                if ($session->uuid === $sessionUuid) {
                    array_splice($node->children, $index, 1);
                    return true;
                }
            }
        }

        foreach ($node->children as $child) {
            if ($this->deleteSessionFromWeek($child, $weekUuid, $sessionUuid)) {
                return true;
            }
        }

        return false;
    }

    protected function findSessionInTree(?TrainingNode $node, string $sessionUuid): ?TrainingNode
    {
        if (!$node) {
            return null;
        }

        if ($node->uuid === $sessionUuid) {
            return $node;
        }

        foreach ($node->children as $child) {
            $found = $this->findSessionInTree($child, $sessionUuid);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    public function render()
    {
        $flat = [];
        if ($this->season) {
            $this->buildFlatList($this->season, $flat);
        }

        $selectedPeriod = null;
        $selectedPeriodType = null;
        if ($this->selectedPeriodUuid && isset($flat[$this->selectedPeriodUuid])) {
            $selectedPeriod = $flat[$this->selectedPeriodUuid];
            $selectedPeriodType = $selectedPeriod->type;
        }

        return view('training-planner', [
            'season' => $this->season,
            'selectedPeriod' => $selectedPeriod,
            'selectedPeriodType' => $selectedPeriodType,
        ]);
    }
}
