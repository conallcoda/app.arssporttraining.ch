<?php

namespace App\Livewire\Training\View;

use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

class Programs extends Component
{
    public Model $exercisePlan;

    public function mount(Model $exercisePlan): void
    {
        $this->exercisePlan = $exercisePlan;
    }

    public function render()
    {
        return view('livewire.training.view.programs');
    }
}
