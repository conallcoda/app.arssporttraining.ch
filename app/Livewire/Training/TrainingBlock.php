<?php

namespace App\Livewire\Training;

use App\Models\Training\Periods\TrainingBlock as TrainingBlockModel;
use Livewire\Component;

class TrainingBlock extends Component
{
    public TrainingBlockModel $block;

    public function render()
    {
        return view('livewire.training.training-block');
    }
}
