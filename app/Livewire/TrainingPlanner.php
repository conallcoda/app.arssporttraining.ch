<?php

namespace App\Livewire;

use App\Models\Training\Periods\TrainingSeason;
use App\Models\Training\TrainingPeriod;
use Livewire\Component;

class TrainingPlanner extends Component
{
    public $season = null;
    public $blocks = [];
    public $activeWeeks = []; // Track active week for each block

    public function mount()
    {
        $tree = TrainingPeriod::get()->toTree();

        if ($tree->isNotEmpty()) {
            $this->season = $tree->first();
            $this->blocks = $this->season->children ?? collect();

            // Initialize active week to 0 (first week) for each block
            foreach ($this->blocks as $index => $block) {
                $this->activeWeeks[$index] = 0;
            }
        }
    }

    public function setActiveWeek($blockIndex, $weekIndex)
    {
        $this->activeWeeks[$blockIndex] = $weekIndex;
    }

    public function render()
    {
        return view('training-planner');
    }
}
