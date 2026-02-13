<?php

namespace App\Livewire\Training;

use App\Models\TrainingPlan;
use App\Models\TrainingPlanProgram;
use Illuminate\Database\Eloquent\Collection;
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
    public string $tab = 'athletes';

    public TrainingPlan $trainingPlan;

    public array $userIds = [];

    public array $userGroupIds = [];

    public Collection $programs;

    public Collection $users;

    public function mount(TrainingPlan $trainingPlan): void
    {
        $this->trainingPlan = $trainingPlan;
        $this->loadAllData();
    }

    protected function loadAllData(): void
    {
        $this->loadAthleteIds();
        $this->loadPrograms();
        $this->loadUsers();
    }

    protected function loadAthleteIds(): void
    {
        $this->trainingPlan->load(['users', 'userGroups']);
        $this->userIds = $this->trainingPlan->users->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->userGroupIds = $this->trainingPlan->userGroups->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    protected function loadPrograms(): void
    {
        $this->programs = TrainingPlanProgram::query()
            ->where('training_plan_id', $this->trainingPlan->id)
            ->with(['exercises' => fn ($q) => $q->orderByPivot('sort')])
            ->orderBy('sort')
            ->get();
    }

    protected function loadUsers(): void
    {
        $this->users = $this->trainingPlan->allUsers()
            ->orderBy('forename')
            ->orderBy('surname')
            ->get();
    }

    protected function loadAthletes(): void
    {
        $this->loadAthleteIds();
        $this->loadUsers();
    }

    public function getDataKey(?string $domain = null): string
    {
        $parts = [
            'athletes' => md5(json_encode([
                $this->userIds,
                $this->userGroupIds,
            ])),
            'programs' => md5(json_encode(
                $this->programs->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sort' => $p->sort,
                    'exercises' => $p->exercises->pluck('id')->all(),
                ])->all()
            )),
            'users' => md5(json_encode($this->users->pluck('id')->all())),
        ];

        if ($domain && isset($parts[$domain])) {
            return $parts[$domain];
        }

        return md5(json_encode($parts));
    }

    public function updatedTab(string $value): void
    {
        $this->dispatch('tab-changed', tab: $value);
    }

    #[On('child-changed')]
    public function handleChildChanged(string $domain): void
    {
        $this->trainingPlan->refresh();

        match ($domain) {
            'athletes' => $this->loadAthletes(),
            'programs' => $this->loadPrograms(),
            'users' => $this->loadUsers(),
            'plan' => $this->trainingPlan->refresh(),
            'schedule' => $this->trainingPlan->refresh(),
            default => $this->loadAllData(),
        };
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
        $this->loadAllData();
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
