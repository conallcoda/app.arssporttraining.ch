<?php

namespace App\Livewire\Training\View;

use App\Data\Training\Config\Schedule\ScheduleWeek;
use App\Data\Training\Config\Schedule\ScheduleWeekCollection;
use App\Form\Fields\Training\Program\Color;
use App\Models\TrainingPlan;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class ScheduleNew extends Component
{
    public TrainingPlan $trainingPlan;

    public Collection $programs;

    public Collection $users;

    #[Url(except: null, as: 'user')]
    public int|string|null $user = null;

    public function updatingUser(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        if ($this->user === null) {
            return null;
        }

        return $this->users->firstWhere('id', $this->user);
    }

    public function selectUser(?int $userId): void
    {
        $this->user = $userId;
        unset($this->schedule);
    }

    #[Computed]
    public function defaultSchedule(): ScheduleWeekCollection
    {
        $weeks = $this->trainingPlan->config->get('default.schedule.weeks', []);

        return ScheduleWeekCollection::fromArray($weeks);
    }

    #[Computed]
    public function schedule(): ScheduleWeekCollection
    {
        $defaultWeeks = $this->trainingPlan->config->get('default.schedule.weeks', []);
        $userOverrides = $this->user !== null
            ? $this->trainingPlan->config->get("users.{$this->user}.schedule.weeks", [])
            : [];

        return ScheduleWeekCollection::fromArrays($defaultWeeks, $userOverrides);
    }

    public function hasUserSchedule(int $userId): bool
    {
        $userOverrides = $this->trainingPlan->config->get("users.{$userId}.schedule.weeks", []);

        if (empty($userOverrides)) {
            return false;
        }

        $defaultWeekIds = $this->defaultSchedule->items()->pluck('id')->all();

        return ScheduleWeekCollection::fromArray($userOverrides)->hasOverrides($defaultWeekIds);
    }

    #[Computed]
    public function programOptions(): array
    {
        return $this->programs->mapWithKeys(fn($program) => [
            $program->id => $program->name,
        ])->all();
    }

    public function getResolvedSlots(ScheduleWeek $week): array
    {
        if ($week->linkedTo === null) {
            return $week->grid();
        }

        $sourceWeek = $this->schedule->findById($week->linkedTo);

        return $sourceWeek ? $this->getResolvedSlots($sourceWeek) : $week->grid();
    }

    public function getProgramColor(?int $programId): string
    {
        if ($programId === null) {
            return Color::DEFAULT_COLOR;
        }

        $program = $this->programs->firstWhere('id', $programId);

        return $program?->config->get('color', Color::DEFAULT_COLOR) ?? Color::DEFAULT_COLOR;
    }

    public function render()
    {
        return view('livewire.training.view.schedule-new');
    }
}
