<?php

namespace App\Livewire\Training\View;

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
    public function defaultSchedule(): array
    {
        return $this->trainingPlan->config->get('default.schedule.weeks', []);
    }

    #[Computed]
    public function schedule(): array
    {
        return $this->resolveScheduleForUser();
    }

    protected function resolveScheduleForUser(): array
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

        $weeks = collect($defaultWeeks)->map(function ($week, $index) use ($userOverrides) {
            $weekId = $week['id'];
            $override = $this->findUserWeekOverride($userOverrides, $weekId);

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

        $userAddedWeeks = collect($userOverrides)
            ->filter(fn($override) => ! in_array($override['id'] ?? null, $defaultWeekIds));

        return $weeks->merge($userAddedWeeks)
            ->sortBy('sort')
            ->values()
            ->all();
    }

    protected function findUserWeekOverride(array $userWeeks, string $weekId): ?array
    {
        foreach ($userWeeks as $week) {
            if (($week['id'] ?? null) === $weekId) {
                return $week;
            }
        }

        return null;
    }

    protected function mergeSlotOverrides(array $baseSlots, array $overrideSlots): array
    {
        $result = $baseSlots;

        foreach ($overrideSlots as $override) {
            $day = $override['day'];
            $slot = $override['slot'];
            $programId = $override['programId'] ?? null;

            $result = array_filter($result, fn($s) => ! ($s['day'] === $day && $s['slot'] === $slot));

            if ($programId !== null) {
                $result[] = $override;
            }
        }

        return array_values($result);
    }

    public function hasUserSchedule(int $userId): bool
    {
        $userOverrides = $this->trainingPlan->config->get("users.{$userId}.schedule.weeks", []);

        if (empty($userOverrides)) {
            return false;
        }

        $defaultWeekIds = collect($this->defaultSchedule)->pluck('id')->all();

        foreach ($userOverrides as $weekOverride) {
            $weekId = $weekOverride['id'] ?? null;

            if (! in_array($weekId, $defaultWeekIds)) {
                return true;
            }

            if (! empty($weekOverride['removed']) || ! empty($weekOverride['slots']) || array_key_exists('linkedTo', $weekOverride)) {
                return true;
            }
        }

        return false;
    }

    public function countUserScheduleChanges(int $userId): int
    {
        $userOverrides = $this->trainingPlan->config->get("users.{$userId}.schedule.weeks", []);

        if (empty($userOverrides)) {
            return 0;
        }

        $defaultWeekIds = collect($this->defaultSchedule)->pluck('id')->all();
        $count = 0;

        foreach ($userOverrides as $weekOverride) {
            $weekId = $weekOverride['id'] ?? null;

            if (! in_array($weekId, $defaultWeekIds)) {
                $count++;

                continue;
            }

            if (! empty($weekOverride['removed'])) {
                $count++;

                continue;
            }

            if (array_key_exists('linkedTo', $weekOverride)) {
                $count++;
            }

            $count += count($weekOverride['slots'] ?? []);
        }

        return $count;
    }

    #[Computed]
    public function programOptions(): array
    {
        return $this->programs->mapWithKeys(fn($program) => [
            $program->id => $program->name,
        ])->all();
    }

    public function getResolvedSlots(array $week): array
    {
        if ($week['linkedTo'] === null) {
            return $this->sparseToDense($week['slots'] ?? []);
        }

        $sourceWeek = collect($this->schedule)->firstWhere('id', $week['linkedTo']);

        return $sourceWeek ? $this->getResolvedSlots($sourceWeek) : $this->sparseToDense($week['slots'] ?? []);
    }

    protected function sparseToDense(array $sparseSlots): array
    {
        $dense = [];
        for ($day = 0; $day < 7; $day++) {
            $dense[$day] = [
                0 => ['programId' => null],
                1 => ['programId' => null],
            ];
        }

        foreach ($sparseSlots as $slot) {
            $day = $slot['day'];
            $slotIndex = $slot['slot'];
            $dense[$day][$slotIndex] = [
                'programId' => $slot['programId'],
            ];
        }

        return $dense;
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
