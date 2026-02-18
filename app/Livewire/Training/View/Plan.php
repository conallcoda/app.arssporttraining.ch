<?php

namespace App\Livewire\Training\View;

use App\Data\Training\Config\TrainingPlanConfig;
use App\Models\TrainingPlan;
use App\Models\Users\User;
use App\Support\WeekOptions;
use Coda\Cms\Livewire\Concerns\InteractsWithParentView;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Plan extends Component
{
    use InteractsWithParentView;

    public TrainingPlan $trainingPlan;

    public Collection $programs;

    public Collection $users;

    #[Url(except: null, as: 'user')]
    public int|string|null $user = null;

    public ?string $startDate = null;

    public function updatingUser(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function mount(
        TrainingPlan $trainingPlan,
        Collection $programs,
        Collection $users,
    ): void {
        $this->trainingPlan = $trainingPlan;
        $this->programs = $programs;
        $this->users = $users;
        $this->loadStartDate();
    }

    #[On('child-changed')]
    public function handleChildChanged(string $domain): void
    {
        if ($domain === 'schedule') {
            $this->trainingPlan->refresh();
            unset($this->scheduleWeeks);
            unset($this->programIdsFromSchedule);
        }
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        if ($this->user === null) {
            return null;
        }

        return $this->users->firstWhere('id', $this->user);
    }

    #[Computed]
    public function scheduleWeeks(): array
    {
        $config = TrainingPlanConfig::from($this->trainingPlan->config->all());

        if ($this->user !== null && ! $config->isUserScheduleLocked($this->user)) {
            return $config->userScheduleWeeks($this->user);
        }

        return $config->defaultScheduleWeeks();
    }

    #[Computed]
    public function programIdsFromSchedule(): array
    {
        $programIds = [];
        $weeks = $this->scheduleWeeks;

        foreach ($weeks as $week) {
            $slots = $this->getResolvedSlotsForWeek($week, $weeks);
            foreach ($slots as $slot) {
                foreach ($slot['programs'] ?? [] as $programId) {
                    if (! in_array($programId, $programIds)) {
                        $programIds[] = $programId;
                    }
                }
            }
        }

        return $programIds;
    }

    protected function getResolvedSlotsForWeek(array $week, array $allWeeks): array
    {
        if (($week['linkedTo'] ?? null) === null) {
            return $week['slots'] ?? [];
        }

        $sourceWeek = collect($allWeeks)->firstWhere('id', $week['linkedTo']);

        return $sourceWeek ? $this->getResolvedSlotsForWeek($sourceWeek, $allWeeks) : ($week['slots'] ?? []);
    }

    #[Computed]
    public function weeks(): int
    {
        return count($this->scheduleWeeks);
    }

    #[Computed]
    public function weekOptions(): array
    {
        return WeekOptions::generate();
    }

    public function selectUser(?int $userId): void
    {
        $this->user = $userId;
        $this->loadStartDate();
        $this->dispatch('plan-user-changed', userId: $userId);
    }

    protected function loadStartDate(): void
    {
        $config = TrainingPlanConfig::from($this->trainingPlan->config->all());

        if ($this->user === null) {
            $startDate = $config->defaultScheduleStartDate();
            $this->startDate = ! empty($startDate) ? $startDate : WeekOptions::getCurrentWeekValue();
        } else {
            $startDate = $config->userScheduleStartDate($this->user);
            $this->startDate = ! empty($startDate) ? $startDate : WeekOptions::getCurrentWeekValue();
        }

        unset($this->scheduleWeeks);
        unset($this->programIdsFromSchedule);
    }

    public function updated(string $property): void
    {
        if ($property !== 'startDate') {
            return;
        }

        if ($this->user === null) {
            $this->trainingPlan->config->set('default.schedule.startDate', $this->startDate);
        } else {
            $this->trainingPlan->config->set("users.{$this->user}.schedule.startDate", $this->startDate);
        }

        $this->trainingPlan->save();
        $this->trainingPlan->refresh();
    }

    public function render()
    {
        return view('livewire.training.view.plan');
    }
}
