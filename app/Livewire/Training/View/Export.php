<?php

namespace App\Livewire\Training\View;

use App\Livewire\Concerns\InteractsWithParentView;
use App\Models\TrainingPlan;
use Livewire\Component;

class Export extends Component
{
    use InteractsWithParentView;

    public TrainingPlan $trainingPlan;

    public function mount(TrainingPlan $trainingPlan): void
    {
        $this->trainingPlan = $trainingPlan;
    }

    public function render()
    {
        return view('livewire.training.view.export');
    }
}
