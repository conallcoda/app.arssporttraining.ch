<?php

namespace App\Livewire\Training\View;

use App\Models\ProgramCategory;
use App\Models\TrainingPlan;
use App\Models\Users\User;
use App\Support\WeekOptions;
use Coda\Cms\Livewire\Concerns\InteractsWithParentView;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
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

    public ?int $selectedCategoryId = null;

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
        $this->initializeSelectedCategory();
    }

    protected function initializeSelectedCategory(): void
    {
        $categories = $this->programCategories;

        if ($categories->isNotEmpty() && $this->selectedCategoryId === null) {
            $this->selectedCategoryId = $categories->first()->id;
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
        $config = $this->trainingPlan->config;

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

    #[Computed]
    public function programCategories(): Collection
    {
        $scheduledProgramIds = $this->programIdsFromSchedule;
        $scheduledPrograms = $this->programs->whereIn('id', $scheduledProgramIds);

        $categoryIds = $scheduledPrograms
            ->pluck('program_category_id')
            ->filter()
            ->unique()
            ->values();

        return ProgramCategory::whereIn('id', $categoryIds)
            ->orderBy('sort')
            ->get();
    }

    #[Computed]
    public function programsForCategory(): Collection
    {
        if ($this->selectedCategoryId === null) {
            return new Collection;
        }

        $scheduledProgramIds = $this->programIdsFromSchedule;

        return $this->programs
            ->where('program_category_id', $this->selectedCategoryId)
            ->whereIn('id', $scheduledProgramIds)
            ->load(['exercises' => fn ($q) => $q->orderByPivot('sort')])
            ->values();
    }

    #[Computed]
    public function sessionsPerWeekByProgram(): array
    {
        $weeks = $this->scheduleWeeks;
        $templateWeek = collect($weeks)->first(fn (array $w) => ($w['linkedTo'] ?? null) === null) ?? ($weeks[0] ?? null);

        if ($templateWeek === null) {
            return [];
        }

        $slots = $templateWeek['slots'] ?? [];
        $counts = [];

        foreach ($slots as $slot) {
            foreach ($slot['programs'] ?? [] as $programId) {
                $counts[$programId] = ($counts[$programId] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function selectUser(?int $userId): void
    {
        $this->user = $userId;
        $this->loadStartDate();
        $this->clearCategoryComputedProperties();
        $this->dispatch('plan-user-changed', userId: $userId);
    }

    public function selectCategory(int $categoryId): void
    {
        $this->selectedCategoryId = $categoryId;
        unset($this->programsForCategory);
    }

    protected function loadStartDate(): void
    {
        $config = $this->trainingPlan->config;

        $startDate = $this->user === null
            ? $config->defaultScheduleStartDate()
            : $config->userScheduleStartDate($this->user);

        $this->startDate = ! empty($startDate) ? $startDate : WeekOptions::getCurrentWeekValue();

        unset($this->scheduleWeeks);
        unset($this->programIdsFromSchedule);
    }

    protected function clearCategoryComputedProperties(): void
    {
        unset($this->programCategories);
        unset($this->programsForCategory);
        unset($this->sessionsPerWeekByProgram);
    }

    public function updated(string $property): void
    {
        if ($property !== 'startDate') {
            return;
        }

        $this->notifyDataChanged('startDate', [
            'userId' => $this->user,
            'startDate' => $this->startDate,
        ]);
    }

    public function render()
    {
        return view('livewire.training.view.plan');
    }
}
