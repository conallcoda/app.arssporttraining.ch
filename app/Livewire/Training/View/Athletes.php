<?php

namespace App\Livewire\Training\View;

use App\Form\Fields\Athlete\Athletes as AthletesField;
use App\Form\Fields\AthleteGroup\Groups;
use App\Models\TrainingPlan;
use Coda\Cms\Livewire\Concerns\InteractsWithParentView;
use Livewire\Component;

class Athletes extends Component
{
    use InteractsWithParentView;

    public TrainingPlan $trainingPlan;

    public array $users = [];

    public array $userGroups = [];

    public function mount(
        TrainingPlan $trainingPlan,
        array $userIds = [],
        array $userGroupIds = [],
    ): void {
        $this->trainingPlan = $trainingPlan;
        $this->users = $userIds;
        $this->userGroups = $userGroupIds;
    }

    public function updated(string $property): void
    {
        $this->notifyDataChanged('athletes', [
            'userIds' => $this->users,
            'userGroupIds' => $this->userGroups,
        ]);
    }

    public function getFields(): array
    {
        return [
            AthletesField::make('users')->withOptions()->live()->debounce(300),
            Groups::make('userGroups')->withOptions()->live()->debounce(300),
        ];
    }

    public function render()
    {
        return view('livewire.training.view.athletes', [
            'fields' => $this->getFields(),
        ]);
    }
}
