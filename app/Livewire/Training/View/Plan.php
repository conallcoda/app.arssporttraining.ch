<?php

namespace App\Livewire\Training\View;

use App\Livewire\Concerns\InteractsWithParentView;
use App\Models\Training\Progression\Reference\RepPercentageTable;
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

    public ?int $measured_reps = null;

    public ?float $measured_weight = null;

    public ?int $target_goal = null;

    public function mount(TrainingPlan $trainingPlan): void
    {
        $this->trainingPlan = $trainingPlan;
        $this->loadAthleteData();
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
        if ($this->user === null || $this->user === 0) {
            return null;
        }

        return $this->users->firstWhere('id', $this->user);
    }

    #[Computed]
    public function defaultData(): AthleteTrainingProgramData
    {
        return AthleteTrainingProgramData::fromTrainingPlan($this->trainingPlan, null);
    }

    #[Computed]
    public function estimatedOneRepMax(): ?float
    {
        $reps = $this->measured_reps ?? ($this->user === 0 ? AthleteTrainingProgramData::DEFAULT_MEASURED_REPS : null);
        $weight = $this->measured_weight ?? ($this->user === 0 ? AthleteTrainingProgramData::DEFAULT_MEASURED_WEIGHT : null);

        if ($weight === null || $weight <= 0 || $reps === null || $reps < 1) {
            return null;
        }

        $percentage = RepPercentageTable::getPercentage($reps);

        return round($weight / $percentage, 1);
    }

    #[Computed]
    public function targetOneRepMax(): ?float
    {
        $startingMax = $this->estimatedOneRepMax;
        $goal = $this->target_goal ?? ($this->user === 0 ? AthleteTrainingProgramData::DEFAULT_TARGET_GOAL : $this->defaultData->target_goal);

        if ($startingMax === null || $goal === null) {
            return null;
        }

        return round($startingMax * (1 + $goal / 100), 1);
    }

    public function selectUser(int $userId): void
    {
        $this->user = $userId;
        $this->loadAthleteData();
    }

    public function loadAthleteData(): void
    {
        if ($this->user === null) {
            $this->measured_reps = null;
            $this->measured_weight = null;
            $this->target_goal = null;

            return;
        }

        $userId = $this->user === 0 ? null : $this->user;
        $data = AthleteTrainingProgramData::fromTrainingPlan($this->trainingPlan, $userId);

        $this->measured_reps = $data->measured_reps;
        $this->measured_weight = $data->measured_weight;
        $this->target_goal = $data->target_goal;
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['measured_reps', 'measured_weight', 'target_goal'])) {
            return;
        }

        if ($this->user === null) {
            return;
        }

        $userId = $this->user === 0 ? null : $this->user;

        $data = new AthleteTrainingProgramData(
            measured_reps: $this->measured_reps,
            measured_weight: $this->measured_weight,
            target_goal: $this->target_goal,
        );

        $data->persist($this->trainingPlan, $userId);
        $this->trainingPlan->refresh();
    }

    public function getPlaceholder(string $field): mixed
    {
        if ($this->user === 0 || $this->user === null) {
            return null;
        }

        if ($field !== 'target_goal') {
            return null;
        }

        return $this->defaultData->{$field};
    }

    public function render()
    {
        return view('livewire.training.view.plan');
    }
}
