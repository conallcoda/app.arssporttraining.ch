<?php

namespace App\Livewire\Training\View;

use App\Data\Training\Config\Schedule\ScheduleWeek;
use App\Data\Training\Config\Schedule\ScheduleWeekCollection;
use App\Data\Training\Config\TrainingPlanConfig;
use App\Form\Fields\Training\Program\Color;
use App\Models\TrainingPlan;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
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
    public function config(): TrainingPlanConfig
    {
        return TrainingPlanConfig::from($this->trainingPlan->config->all());
    }

    #[Computed]
    public function defaultSchedule(): ScheduleWeekCollection
    {
        return ScheduleWeekCollection::fromArray($this->config->defaultScheduleWeeks());
    }

    #[Computed]
    public function schedule(): ScheduleWeekCollection
    {
        $defaultWeeks = $this->config->defaultScheduleWeeks();
        $userOverrides = $this->user !== null
            ? $this->config->userScheduleWeeks($this->user)
            : [];

        return ScheduleWeekCollection::fromArrays($defaultWeeks, $userOverrides);
    }

    public function hasUserSchedule(int $userId): bool
    {
        $userOverrides = $this->config->userScheduleWeeks($userId);

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

    #[On('schedule-event')]
    public function onScheduleEvent(string $type, array $data = []): void
    {
        $handler = new ScheduleHandler($this->trainingPlan, $this->user);
        $handler->handle($type, $data);

        $this->trainingPlan->refresh();
        unset($this->config);
        unset($this->schedule);
        unset($this->defaultSchedule);
    }

    public function render()
    {
        return view('livewire.training.view.schedule-new');
    }
}
