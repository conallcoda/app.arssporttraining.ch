<?php

namespace App\Livewire;

use App\Models\Training\Periods\TrainingSeason;
use App\Models\Training\TrainingPeriod;
use Livewire\Component;

class TrainingPlanner extends Component
{
    public $season = null;

    public function mount()
    {
        $model = TrainingPeriod::where('type', 'season')->first();
        $dto = TrainingSeason::from($model);
        $this->season = $dto;
    }

    public function render()
    {
        return view('training-planner');
    }
}
