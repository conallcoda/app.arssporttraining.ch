<?php

namespace App\Livewire\Training;

use App\Models\Training\TrainingNode;
use Livewire\Component;

class TrainingBlock extends Component
{
    public TrainingNode $block;

    public function render()
    {
        return view('livewire.training.training-block');
    }
}
