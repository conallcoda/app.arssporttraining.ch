<?php

namespace App\Livewire;

use App\Models\Training\TrainingPeriod;
use App\Models\Training\TrainingNode;
use Livewire\Component;


class TrainingPlanner extends Component
{
    public $expanded = [];
    public $maxDepth = 2;
    public $selectedPeriodUuid = null;
    public ?TrainingNode $season = null;

    public function mount($maxDepth = 2)
    {
        $this->maxDepth = $maxDepth;

        $seasonModel = TrainingPeriod::where('type', 'season')->first();

        if ($seasonModel) {
            $tree = TrainingPeriod::withMaxDepth($maxDepth + 1, function() use ($seasonModel) {
                return $seasonModel->descendantsAndSelf()
                    ->orderBy('sequence')
                    ->get()
                    ->toTree();
            });

            if ($tree->isNotEmpty()) {
                $seasonModel = $tree->first();
                $this->expandInitialNodes($seasonModel, 0);

                $this->season = TrainingNode::fromModel($seasonModel);
                if (!empty($this->season->children)) {
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

    protected function buildFlatList(TrainingNode $node, array &$flat): void
    {
        $flat[$node->uuid] = $node;
        foreach ($node->children as $child) {
            $this->buildFlatList($child, $flat);
        }
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
