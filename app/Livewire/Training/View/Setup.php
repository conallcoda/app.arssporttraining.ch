<?php

namespace App\Livewire\Training\View;

use App\Data\Form\FluxField;
use App\Livewire\Concerns\InteractsWithParentView;
use App\Models\TrainingPlan;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Models\Users\UserTypeEnum;
use App\Support\WeekOptions;
use Livewire\Component;

class Setup extends Component
{
    use InteractsWithParentView;

    public TrainingPlan $trainingPlan;

    public ?string $startDate = null;

    public ?int $duration = null;

    public array $users = [];

    public array $userGroups = [];

    public function mount(TrainingPlan $trainingPlan): void
    {
        $this->trainingPlan = $trainingPlan->load(['users', 'userGroups']);

        $this->startDate = $trainingPlan->start_date?->format('Y-m-d');
        $this->duration = $trainingPlan->duration;

        $this->users = $trainingPlan->users->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->userGroups = $trainingPlan->userGroups->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function updated(string $property): void
    {
        $this->save();
    }

    protected function save(): void
    {
        $this->trainingPlan->start_date = $this->startDate;
        $this->trainingPlan->duration = $this->duration;
        $this->trainingPlan->save();

        $this->trainingPlan->users()->sync($this->users);
        $this->trainingPlan->userGroups()->sync($this->userGroups);

        $this->notifyRefresh();
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
            FluxField::select('startDate')
                ->label('Start Date')
                ->options(WeekOptions::generate())
                ->default(WeekOptions::getCurrentWeekValue())
                ->live(),
            FluxField::text('duration')
                ->label('Duration')
                ->suffix('weeks')
                ->default(5)
                ->live(),
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
        return view('livewire.training.view.setup', [
            'fields' => $this->getFields(),
        ]);
    }
}
