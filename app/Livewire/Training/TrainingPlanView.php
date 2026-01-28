<?php

namespace App\Livewire\Training;

use App\Models\TrainingPlan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.database')]
#[Title('ARS - Athlete Training // Training Plan')]
class TrainingPlanView extends Component
{
    #[Url]
    public string $tab = 'setup';

    public TrainingPlan $trainingPlan;

    public function updatedTab(string $value): void
    {
        $this->dispatch('tab-changed', tab: $value);
    }

    #[On('data-changed')]
    public function handleDataChanged(string $key, mixed $value): void
    {
        //
    }

    #[On('refresh-requested')]
    public function handleRefreshRequested(): void
    {
        $this->trainingPlan->refresh();
    }

    public function updateName(string $name): void
    {
        $this->trainingPlan->name = $name;
        $this->trainingPlan->save();
    }

    public function render()
    {
        return view('livewire.training.training-plan-view');
    }
}
