<?php

namespace App\Livewire\Test\Athlete;

use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AthleteSelector extends Component
{
    public ?int $selectedAthleteId = null;

    public function mount(): void
    {
        $this->selectedAthleteId = session('athlete.selectedId');

        if ($this->selectedAthleteId === null) {
            $firstAthlete = $this->athletes->first();

            if ($firstAthlete) {
                $this->selectedAthleteId = $firstAthlete->id;
                session(['athlete.selectedId' => $this->selectedAthleteId]);
            }
        }
    }

    public function updatedSelectedAthleteId(?int $value): void
    {
        session(['athlete.selectedId' => $value]);
        $this->dispatch('athlete-selected', athleteId: $value);
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function athletes(): Collection
    {
        return User::query()
            ->where('type', UserTypeEnum::Athlete)
            ->where(function ($query) {
                $query->whereExists(function ($sub) {
                    $sub->select('training_plan_user.id')
                        ->from('training_plan_user')
                        ->whereColumn('training_plan_user.user_id', 'users.id')
                        ->whereExists(function ($planCheck) {
                            $planCheck->select('training_plans.id')
                                ->from('training_plans')
                                ->whereColumn('training_plans.id', 'training_plan_user.training_plan_id')
                                ->whereNull('training_plans.deleted_at');
                        });
                })->orWhereExists(function ($sub) {
                    $sub->select('user_group_memberships.id')
                        ->from('user_group_memberships')
                        ->whereColumn('user_group_memberships.user_id', 'users.id')
                        ->whereExists(function ($groupPlanCheck) {
                            $groupPlanCheck->select('training_plan_user_group.id')
                                ->from('training_plan_user_group')
                                ->whereColumn('training_plan_user_group.user_group_id', 'user_group_memberships.user_group_id')
                                ->whereExists(function ($planCheck) {
                                    $planCheck->select('training_plans.id')
                                        ->from('training_plans')
                                        ->whereColumn('training_plans.id', 'training_plan_user_group.training_plan_id')
                                        ->whereNull('training_plans.deleted_at');
                                });
                        });
                });
            })
            ->orderBy('forename')
            ->orderBy('surname')
            ->get();
    }

    public function render()
    {
        return view('livewire.test.athlete.athlete-selector');
    }
}
