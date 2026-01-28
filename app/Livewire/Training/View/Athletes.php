<?php

namespace App\Livewire\Training\View;

use App\Data\Form\FluxField;
use App\Livewire\Concerns\InteractsWithParentView;
use App\Models\TrainingPlan;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Models\Users\UserTypeEnum;
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
        $this->save();
    }

    protected function save(): void
    {
        $this->trainingPlan->users()->sync($this->users);
        $this->trainingPlan->userGroups()->sync($this->userGroups);

        $this->notifyChanged('athletes');
    }

    public function getFields(): array
    {
        $athleteOptions = User::where('type', UserTypeEnum::Athlete)
            ->orderBy('forename')
            ->orderBy('surname')
            ->get()
            ->mapWithKeys(fn ($user) => [$user->id => $user->name])
            ->all();

        $groupOptions = UserGroup::orderBy('name')
            ->get()
            ->mapWithKeys(fn ($group) => [$group->id => $group->name])
            ->all();

        return [
            FluxField::pillbox('users')
                ->label('Athletes')
                ->options($athleteOptions)
                ->searchable()
                ->placeholder('Select athletes...'),
            FluxField::pillbox('userGroups')
                ->label('Groups')
                ->options($groupOptions)
                ->searchable()
                ->placeholder('Select groups...'),
        ];
    }

    public function render()
    {
        return view('livewire.training.view.athletes', [
            'fields' => $this->getFields(),
        ]);
    }
}
