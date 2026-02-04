<?php

namespace App\Livewire\Training\View;

use App\Form\Fields\Pillbox;
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
        $this->trainingPlan->users()->sync($this->buildSyncDataWithSort(
            $this->users,
            $this->trainingPlan->users()->pluck('sort', 'user_id')->all()
        ));

        $this->trainingPlan->userGroups()->sync($this->buildSyncDataWithSort(
            $this->userGroups,
            $this->trainingPlan->userGroups()->pluck('sort', 'user_group_id')->all()
        ));

        $this->notifyChanged('athletes');
    }

    protected function buildSyncDataWithSort(array $ids, array $existingSorts): array
    {
        $maxSort = empty($existingSorts) ? -1 : max($existingSorts);
        $result = [];

        foreach ($ids as $id) {
            if (isset($existingSorts[$id])) {
                $result[$id] = ['sort' => $existingSorts[$id]];
            } else {
                $maxSort++;
                $result[$id] = ['sort' => $maxSort];
            }
        }

        return $result;
    }

    public function getFields(): array
    {
        $athleteOptions = User::where('type', UserTypeEnum::Athlete)
            ->orderBy('forename')
            ->orderBy('surname')
            ->get()
            ->mapWithKeys(fn($user) => [$user->id => $user->name])
            ->all();

        $groupOptions = UserGroup::orderBy('name')
            ->get()
            ->mapWithKeys(fn($group) => [$group->id => $group->name])
            ->all();

        return [
            Pillbox::make('users')
                ->label('Athletes')
                ->options($athleteOptions)
                ->searchable()
                ->placeholder('Select athletes...'),
            Pillbox::make('userGroups')
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
