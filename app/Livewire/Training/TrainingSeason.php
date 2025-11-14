<?php

namespace App\Livewire\Training;

use App\Models\Training\Periods\TrainingSeason as TrainingSeasonModel;
use Livewire\Component;

class TrainingSeason extends Component
{
    public TrainingSeasonModel $season;

    public function render()
    {
        return view('livewire.training.training-season');
    }
}
