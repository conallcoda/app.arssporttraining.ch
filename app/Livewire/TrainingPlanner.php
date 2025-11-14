<?php

namespace App\Livewire;

use App\Models\Training\TrainingPeriod;
use Livewire\Component;

class TrainingPlanner extends Component
{
    public $season = null;

    public function mount()
    {
        $tree = TrainingPeriod::get()->toTree();

        if ($tree->isNotEmpty()) {
            $this->season = $tree->first();
        }
    }

    public function render()
    {
        return view('training-planner');
    }
}
