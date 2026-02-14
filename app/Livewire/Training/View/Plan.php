<?php

namespace App\Livewire\Training\View;

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
        $defaultWeeks = $this->trainingPlan->config->get('default.schedule.weeks', []);

        if ($this->user === null) {
            return $defaultWeeks;
        }

        $userOverrides = $this->trainingPlan->config->get("users.{$this->user}.schedule.weeks", []);

        if (empty($userOverrides)) {
            return $defaultWeeks;
        }

        $defaultWeekIds = collect($defaultWeeks)->pluck('id')->all();

        $userOverridesCollection = collect($userOverrides);

        $weeks = collect($defaultWeeks)->map(function ($week, $index) use ($userOverridesCollection) {
            $weekId = $week['id'];
            $override = $userOverridesCollection->firstWhere('id', $weekId);

            if (! isset($week['sort'])) {
                $week['sort'] = $index;
            }

            if (! $override) {
                return $week;
            }

            if (! empty($override['removed'])) {
                return null;
            }

            if (array_key_exists('linkedTo', $override)) {
                $week['linkedTo'] = $override['linkedTo'];
            }

            if (! empty($override['slots'])) {
                $week['slots'] = $this->mergeSlotOverrides($week['slots'] ?? [], $override['slots']);
            }

            return $week;
        })->filter();

        $userAddedWeeks = $userOverridesCollection
            ->filter(fn ($override) => ! in_array($override['id'] ?? null, $defaultWeekIds));

        return $weeks->merge($userAddedWeeks)
            ->sortBy('sort')
            ->values()
            ->all();
    }

    protected function mergeSlotOverrides(array $baseSlots, array $overrideSlots): array
    {
        $result = $baseSlots;

        foreach ($overrideSlots as $override) {
            $day = $override['day'];
            $slot = $override['slot'];
            $programId = $override['programId'] ?? null;

            $result = array_filter($result, fn ($s) => ! ($s['day'] === $day && $s['slot'] === $slot));

            if ($programId !== null) {
                $result[] = $override;
            }
        }

        return array_values($result);
    }

    #[Computed]
    public function programIdsFromSchedule(): array
    {
        $programIds = [];
        $weeks = $this->scheduleWeeks;

        foreach ($weeks as $week) {
            $slots = $this->getResolvedSlotsForWeek($week, $weeks);
            foreach ($slots as $slot) {
                $programId = $slot['programId'] ?? null;
                if ($programId !== null && ! in_array($programId, $programIds)) {
                    $programIds[] = $programId;
                }
            }
        }

        return $programIds;
    }

    protected function getResolvedSlotsForWeek(array $week, array $allWeeks): array
    {
        if ($week['linkedTo'] === null) {
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
        if ($this->user === null) {
            $startDate = $this->trainingPlan->config->get('default.schedule.startDate');
            $this->startDate = ! empty($startDate) ? $startDate : WeekOptions::getCurrentWeekValue();
        } else {
            $userStartDate = $this->trainingPlan->config->get("users.{$this->user}.schedule.startDate");
            $defaultStartDate = $this->trainingPlan->config->get('default.schedule.startDate');
            $this->startDate = ! empty($userStartDate) ? $userStartDate : (! empty($defaultStartDate) ? $defaultStartDate : WeekOptions::getCurrentWeekValue());
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
            $defaultStartDate = $this->trainingPlan->config->get('default.schedule.startDate');

            if ($this->startDate === $defaultStartDate) {
                $this->trainingPlan->config->forget("users.{$this->user}.schedule.startDate");
            } else {
                $this->trainingPlan->config->set("users.{$this->user}.schedule.startDate", $this->startDate);
            }
        }

        $this->trainingPlan->save();
        $this->trainingPlan->refresh();
    }

    public function render()
    {
        return view('livewire.training.view.plan');
    }
}
