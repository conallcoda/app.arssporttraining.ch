<?php

namespace App\Livewire\Training\View;

use App\Livewire\Concerns\InteractsWithParentView;
use App\Models\TrainingPlan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Programs extends Component
{
    use InteractsWithParentView;

    public TrainingPlan $trainingPlan;

    public function mount(TrainingPlan $trainingPlan): void
    {
        $this->trainingPlan = $trainingPlan;
    }

    #[Computed]
    public function programs(): array
    {
        return $this->trainingPlan->programs()->with('exercises')->get()->all();
    }

    #[On('training-programs-updated')]
    public function refreshPrograms(): void
    {
        unset($this->programs);
    }

    public function render()
    {
        return view('livewire.training.view.programs');
    }
}
