<?php

namespace App\Livewire\Training\View;

use App\Livewire\Concerns\InteractsWithParentView;
use App\Models\TrainingPlan;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class Plan extends Component
{
    use InteractsWithParentView;

    public TrainingPlan $trainingPlan;

    #[Url]
    public ?int $user = null;

    public function mount(TrainingPlan $trainingPlan): void
    {
        $this->trainingPlan = $trainingPlan;
    }

    #[Computed]
    public function users(): Collection
    {
        return $this->trainingPlan->allUsers()
            ->orderBy('forename')
            ->orderBy('surname')
            ->get();
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        if ($this->user === null) {
            return null;
        }

        return $this->users->firstWhere('id', $this->user);
    }

    public function selectUser(int $userId): void
    {
        $this->user = $userId;
    }

    public function render()
    {
        return view('livewire.training.view.plan');
    }
}
