<?php

namespace App\Livewire\Training\View;

use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

class Programs extends Component
{
    public Model $trainingPlan;

    public function mount(Model $trainingPlan): void
    {
        $this->trainingPlan = $trainingPlan;
    }

    public function render()
    {
        return view('livewire.training.view.programs');
    }
}
