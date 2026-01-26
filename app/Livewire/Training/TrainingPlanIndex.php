<?php

namespace App\Livewire\Training;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.database')]
#[Title('ARS - Athlete Training // Training Plans')]
class TrainingPlanIndex extends Component
{
    public function render()
    {
        return view('livewire.training.training-plan-index');
    }
}
