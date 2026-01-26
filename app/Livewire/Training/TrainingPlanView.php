<?php

namespace App\Livewire\Training;

use App\Models\TrainingPlan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.database')]
#[Title('ARS - Athlete Training // Training Plan')]
class TrainingPlanView extends Component
{
    public TrainingPlan $trainingPlan;

    public function render()
    {
        return view('livewire.training.training-plan-view');
    }
}
