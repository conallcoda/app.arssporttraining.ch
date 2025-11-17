<?php

namespace App\Livewire;

use App\Models\Training\Periods\TrainingSeason;
use Livewire\Component;

class TrainingPlanner extends Component
{
    public $season = null;

    public function mount()
    {
        $this->season = TrainingSeason::with('children.children.children')
            ->first();
    }

    public function render()
    {
        return view('training-planner');
    }
}
