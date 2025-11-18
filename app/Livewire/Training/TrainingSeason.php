<?php

namespace App\Livewire\Training;

use App\Models\Training\TrainingNode;
use Livewire\Component;

class TrainingSeason extends Component
{
    public TrainingNode $season;

    public function render()
    {
        return view('livewire.training.training-season');
    }
}
