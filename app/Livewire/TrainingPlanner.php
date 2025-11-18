<?php

namespace App\Livewire;

use App\Models\Training\Periods\TrainingBlock;
use App\Models\Training\Periods\TrainingSeason;
use App\Models\Training\Periods\TrainingWeek;
use App\Models\Training\TrainingPeriod;
use Livewire\Component;

class TrainingPlanner extends Component
{
    public $expanded = [];
    public $maxDepth = 2;
    public $selectedWeekId = null;

    public function mount($maxDepth = 2)
    {
        $this->maxDepth = $maxDepth;

        $model = TrainingPeriod::where('type', 'season')->first();
        if ($model) {
            $this->expandInitialNodes($model, 0);
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

    public function selectWeek($weekId)
    {
        $this->selectedWeekId = $weekId;
    }

    public function render()
    {
        $model = TrainingPeriod::where('type', 'season')->first();
        $season = $model ? TrainingSeason::from($model) : null;

        $selectedWeek = null;
        if ($this->selectedWeekId && $season) {
            foreach ($season->children as $block) {
                foreach ($block->children as $week) {
                    if ($week->getIdentity()?->id == $this->selectedWeekId) {
                        $selectedWeek = $week;
                        break 2;
                    }
                }
            }
        }

        return view('training-planner', [
            'season' => $season,
            'selectedWeek' => $selectedWeek,
        ]);
    }
}
