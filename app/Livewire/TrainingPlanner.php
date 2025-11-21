<?php

namespace App\Livewire;

use App\Data\NavigationTree\TreeNode;
use App\Models\Training\Factory\LargeSeasonFactory;
use App\Models\Training\TrainingTree;
use App\Services\TrainingNodeTransformer;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingPeriod;
use App\Models\Training\Factory\SeasonConfig;
use App\Models\Training\Factory\SeasonFactory;


class TrainingPlanner extends Component
{
    #[Url(as: 'period')]
    public $selectedPeriodUuid = null;

    public bool $showHistoryModal = false;

    public TrainingTree $tree;
    protected TrainingNodeTransformer $transformer;

    public function boot()
    {
        $this->transformer = new TrainingNodeTransformer();
    }

    protected function createRootNode($db = false)
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
                numberOfBlocks: 1,
                weeksPerBlock: 2
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

    public function openHistoryModal()
    {
        $this->showHistoryModal = true;
    }

    public function closeHistoryModal()
    {
        $this->showHistoryModal = false;
    }

    #[On('action')]
    public function action($type, $action, $params = [])
    {
        if ($type === 'session' && $action === 'add' && !isset($params['day'])) {
            $this->dispatch('show-add-session-modal', weekUuid: $params['nodeId'] ?? null);
            return;
        }

        if ($action === 'delete') {
            $nodeId = $params['nodeId'] ?? null;
            if ($this->selectedPeriodUuid === $nodeId) {
                $this->selectedPeriodUuid = null;
            }
        }

        if ($action === 'add') {
            $params['parentId'] = $params['nodeId'] ?? $params['parentId'] ?? null;
            unset($params['nodeId']);
        }

        if ($action === 'move' || $action === 'duplicate') {
            $params['nodeId'] = $params['nodeId'] ?? null;
        }

        $this->tree->executeAction("$type.$action", $params);
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
