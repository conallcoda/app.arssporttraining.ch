<?php

namespace App\Livewire\Training;

use App\Models\Training\Periods\TrainingWeek as TrainingWeekModel;
use Livewire\Component;

class TrainingWeek extends Component
{
    public TrainingWeekModel $week;

    public function render()
    {
        return view('livewire.training.training-week');
    }
}
