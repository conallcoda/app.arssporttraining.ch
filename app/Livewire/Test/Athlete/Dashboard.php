<?php

namespace App\Livewire\Test\Athlete;

use App\Livewire\Test\Athlete\Data\AthleteTrainingPlanData;
use App\Models\TrainingPlan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.athlete')]
#[Title('Athlete Dashboard')]
class Dashboard extends Component
{
    public ?int $selectedAthleteId = null;

    public function mount(): void
    {
        $this->selectedAthleteId = session('athlete.selectedId');
    }

    #[On('athlete-selected')]
    public function onAthleteSelected(?int $athleteId): void
    {
        $this->selectedAthleteId = $athleteId;
        unset($this->trainingPlans);
    }

    /** @return AthleteTrainingPlanData[] */
    #[Computed]
    public function trainingPlans(): array
    {
        if (! $this->selectedAthleteId) {
            return [];
        }

        return TrainingPlan::query()
            ->with('programs.programCategory')
            ->where(function ($query) {
                $query->whereHas('users', function ($sub) {
                    $sub->where('users.id', $this->selectedAthleteId);
                })->orWhereHas('userGroups', function ($sub) {
                    $sub->whereHas('members', function ($memberQuery) {
                        $memberQuery->where('users.id', $this->selectedAthleteId);
                    });
                });
            })
            ->orderBy('name')
            ->get()
            ->map(fn (TrainingPlan $plan) => AthleteTrainingPlanData::fromModel($plan, $this->selectedAthleteId))
            ->all();
    }

    public function render()
    {
        return view('livewire.test.athlete.dashboard');
    }
}
