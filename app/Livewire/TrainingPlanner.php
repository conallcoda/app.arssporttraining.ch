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
    public array $flat = [];

    public function mount($maxDepth = 2)
    {
        $this->maxDepth = $maxDepth;

        $seasonModel = TrainingPeriod::where('type', 'season')->first();
        if ($seasonModel) {
            $this->expandInitialNodes($seasonModel, 0);
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

    protected function buildFlatList(TrainingNode $node): void
    {
        $this->flat[$node->uuid] = $node;
        foreach ($node->children as $child) {
            $this->buildFlatList($child);
        }
    }

    public function render()
    {
        $model = TrainingPeriod::where('type', 'season')->first();
        $season = $model ? TrainingNode::fromModel($model) : null;

        $this->flat = [];
        if ($season) {
            $this->buildFlatList($season);
        }

        $selectedPeriod = null;
        $selectedPeriodType = null;
        if ($this->selectedPeriodUuid && isset($this->flat[$this->selectedPeriodUuid])) {
            $selectedPeriod = $this->flat[$this->selectedPeriodUuid];
            $selectedPeriodType = $selectedPeriod->type;
        }

        return view('training-planner', [
            'season' => $season,
            'selectedPeriod' => $selectedPeriod,
            'selectedPeriodType' => $selectedPeriodType,
        ]);
    }
}
