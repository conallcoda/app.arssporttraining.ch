<?php

namespace App\Livewire;

use App\Data\NavigationTree\TreeNode;
use App\Models\Training\TrainingTree;
use App\Services\TrainingNodeTransformer;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Attributes\Session;

use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingPeriod;
use App\Models\Training\Factory\SeasonConfig;
use App\Models\Training\Factory\SeasonFactory;


class TrainingPlanner extends Component
{
    #[Url(as: 'period')]
    public $selectedPeriodUuid = null;

    public TrainingTree $tree;
    protected TrainingNodeTransformer $transformer;

    public function boot()
    {
        $this->transformer = new TrainingNodeTransformer();
    }

    protected function createRootNode($db = true)
    {
        if ($db) {
            $model = TrainingPeriod::where('type', 'season')->first();
            $modelTree = TrainingPeriod::withMaxDepth(PHP_INT_MAX, function () use ($model) {
                return $model->descendantsAndSelf()
                    ->orderBy('sequence')
                    ->get()
                    ->toTree();
            });
            if ($modelTree->isNotEmpty()) {
                $model = $modelTree->first();
                return TrainingNode::fromModel($model);
            }
        } else {
            $seasonConfig = new SeasonConfig(
                numberOfBlocks: 2,
                weeksPerBlock: 5
            );
            return SeasonFactory::create($seasonConfig);
        }
    }

    public function mount()
    {
        $this->tree = TrainingTree::from($this->createRootNode());

        if (!isset($this->tree->root)) {
            return;
        }

        if (!$this->selectedPeriodUuid && !empty($this->tree->root->children)) {
            $firstBlock = $this->tree->root->children[0];
            if (!empty($firstBlock->children)) {
                $firstWeek = $firstBlock->children[0];
                $this->selectedPeriodUuid = $firstWeek->uuid;
            }
        }
    }

    public function selectPeriod($uuid)
    {
        $this->selectedPeriodUuid = $uuid;
    }

    public function selectNode($uuid)
    {
        $this->selectPeriod($uuid);
    }

    public function triggerAction(string $nodeUuid, string $actionName): void
    {
        match ($actionName) {
            'add_block' => $this->tree->addBlock($nodeUuid),
            'add_week' => $this->tree->addWeek($nodeUuid),
            'add_session' => $this->dispatch('show-add-session-modal', weekUuid: $nodeUuid),
            'duplicate' => $this->tree->duplicatePeriod($nodeUuid),
            'move_up' => $this->tree->moveUp($nodeUuid),
            'move_down' => $this->tree->moveDown($nodeUuid),
            'delete' => $this->deletePeriod($nodeUuid),
            default => null,
        };
    }

    #[Computed]
    public function navigationTree(): ?TreeNode
    {
        if (!$this->tree->root) {
            return null;
        }

        return $this->transformer->toNavigationTree($this->tree->root);
    }

    #[Computed]
    public function navigationActions(): array
    {
        $navTree = $this->navigationTree();
        if (!$navTree) {
            return [];
        }

        $actions = [];
        $this->collectNodeActions($navTree, $actions);
        return $actions;
    }

    protected function collectNodeActions(TreeNode $node, array &$actions): void
    {
        $navTree = $this->navigationTree();
        if ($navTree) {
            $actions[$node->uuid] = $this->transformer->getActionsForNode($navTree, $node->uuid);
        }

        foreach ($node->children as $child) {
            $this->collectNodeActions($child, $actions);
        }
    }

    #[Computed]
    public function hasChanges(): bool
    {
        return $this->tree->hasChanges();
    }

    #[Computed]
    public function lastChangeTimestamp(): int
    {
        return $this->tree->lastChangeTimestamp;
    }

    public function saveChanges()
    {
        $this->tree->save();
        $this->mount();
    }

    public function revertChanges()
    {
        $this->tree->revert();
        $this->mount();
    }

    public function deletePeriod($uuid)
    {
        if ($this->selectedPeriodUuid === $uuid) {
            $this->selectedPeriodUuid = null;
        }

        $this->tree->deletePeriod($uuid);
    }

    #[On('addSession')]
    public function addSession(string $weekUuid, int $day, int $slot, int $category, array $exercises = [])
    {
        $this->tree->addSession($weekUuid, $day, $slot, $category, $exercises);
        $this->tree = TrainingTree::from($this->tree->toArray());
    }

    #[On('updateSession')]
    public function updateSession(string $weekUuid, string $sessionUuid, int $category, array $exercises = [])
    {
        $this->tree->updateSession($weekUuid, $sessionUuid, $category, $exercises);
    }

    #[On('moveSession')]
    public function moveSession(string $weekUuid, string $sessionUuid, int $newDay, int $newSlot)
    {
        $this->tree->moveSession($weekUuid, $sessionUuid, $newDay, $newSlot);
    }

    #[On('swapSessions')]
    public function swapSessions(string $weekUuid, string $sessionUuid1, string $sessionUuid2)
    {
        $this->tree->swapSessions($weekUuid, $sessionUuid1, $sessionUuid2);
    }

    #[On('deleteSession')]
    public function deleteSession(string $weekUuid, string $sessionUuid)
    {
        $this->tree->deleteSession($weekUuid, $sessionUuid);
    }

    public function render()
    {
        $selectedPeriod = null;
        $selectedPeriodType = null;
        if ($this->selectedPeriodUuid) {
            $selectedPeriod = $this->tree->getNode($this->selectedPeriodUuid);
            if ($selectedPeriod) {
                $selectedPeriodType = $selectedPeriod->type;
            }
        }

        return view('training-planner', [
            'lastChangeTimestamp' => $this->tree->lastChangeTimestamp,
            'root' => $this->tree->root,
            'selectedPeriod' => $selectedPeriod,
            'selectedPeriodType' => $selectedPeriodType,
        ]);
    }
}
