<?php

namespace App\Livewire\Training\View;

use App\Models\ProgramCategory;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanProgram;
use App\Models\Users\User;
use App\Support\WeekOptions;
use App\Training\Reference\OneRepMaxConversion;
use Coda\Cms\Livewire\Concerns\InteractsWithParentView;
use Flux\Flux;
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

    public ?int $selectedCategoryId = null;

    public ?int $measuredReps = null;

    public ?float $measuredWeight = null;

    public int|float $targetGoal = 10;

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
        $this->loadTarget();
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

    #[Computed]
    public function starting1RM(): ?float
    {
        if ($this->measuredReps === null || $this->measuredWeight === null) {
            return null;
        }

        return OneRepMaxConversion::estimatedOneRepMax($this->measuredReps, $this->measuredWeight);
    }

    #[Computed]
    public function target1RM(): ?float
    {
        $starting = $this->starting1RM;

        if ($starting === null) {
            return null;
        }

        return OneRepMaxConversion::targetOneRepMax($starting, $this->targetGoal);
    }

    #[Computed]
    public function hasDefaultOverrides(): bool
    {
        return $this->trainingPlan->config->hasDefaultOverrides();
    }

    #[Computed]
    public function hasUserOverrides(): bool
    {
        return $this->trainingPlan->config->hasUserOverrides();
    }

    #[Computed]
    public function hasSelectedUserOverrides(): bool
    {
        if ($this->user === null) {
            return false;
        }

        return $this->trainingPlan->config->hasExerciseOverridesForUser($this->user);
    }

    /** @return array<int, int> */
    #[Computed]
    public function userOverrideCounts(): array
    {
        $counts = [];

        foreach ($this->users as $userItem) {
            $count = $this->trainingPlan->config->exerciseOverrideCountForUser($userItem->id);
            if ($count > 0) {
                $counts[$userItem->id] = $count;
            }
        }

        return $counts;
    }

    public function confirmResetDefaultSettings(): void
    {
        Flux::modal('reset-default-settings')->show();
    }

    public function resetDefaultSettings(): void
    {
        $this->notifyDataChanged('resetDefaults', []);
        Flux::modal('reset-default-settings')->close();
        $this->loadStartDate();
        $this->loadTarget();
        $this->clearCategoryComputedProperties();
        unset($this->hasDefaultOverrides);
    }

    public function confirmResetUserSettings(): void
    {
        Flux::modal('reset-user-settings')->show();
    }

    public function resetUserSettings(): void
    {
        $this->notifyDataChanged('resetUserSettings', []);
        Flux::modal('reset-user-settings')->close();
        $this->loadStartDate();
        $this->loadTarget();
        $this->clearCategoryComputedProperties();
        unset($this->hasUserOverrides);
        unset($this->userOverrideCounts);
    }

    public function confirmResetSelectedUserSettings(): void
    {
        Flux::modal('reset-selected-user-settings')->show();
    }

    public function resetSelectedUserSettings(): void
    {
        $this->notifyDataChanged('resetSingleUserSettings', [
            'userId' => $this->user,
        ]);
        Flux::modal('reset-selected-user-settings')->close();
        $this->loadStartDate();
        $this->loadTarget();
        $this->clearCategoryComputedProperties();
        unset($this->hasSelectedUserOverrides);
        unset($this->userOverrideCounts);
        $this->dispatch('plan-overrides-reset');
    }

    public function selectUser(?int $userId): void
    {
        $this->user = $userId;
        $this->loadStartDate();
        $this->loadTarget();
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

    protected function loadTarget(): void
    {
        $config = $this->trainingPlan->config;

        if ($this->user === null) {
            $this->measuredReps = $config->defaultTargetMeasuredReps() ?? 1;
            $this->measuredWeight = $config->defaultTargetMeasuredWeight() ?? 50;
            $this->targetGoal = $config->defaultTargetGoal();
        } else {
            $this->measuredReps = $config->userTargetMeasuredReps($this->user);
            $this->measuredWeight = $config->userTargetMeasuredWeight($this->user);
            $this->targetGoal = $config->userTargetGoal($this->user);
        }

        unset($this->starting1RM);
        unset($this->target1RM);
    }

    protected function clearCategoryComputedProperties(): void
    {
        unset($this->programCategories);
        unset($this->programsForCategory);
        unset($this->sessionsPerWeekByProgram);
    }

    #[On('exercise-overrides-changed')]
    public function onExerciseOverridesChanged(): void
    {
        $this->trainingPlan->refresh();
        unset($this->hasDefaultOverrides);
        unset($this->hasUserOverrides);
        unset($this->hasSelectedUserOverrides);
        unset($this->userOverrideCounts);
    }

    #[On('parent-data-saved')]
    public function onParentDataSaved(): void
    {
        $this->trainingPlan->refresh();
        $this->programs = TrainingPlanProgram::query()
            ->where('training_plan_id', $this->trainingPlan->id)
            ->with([
                'exercises' => fn ($q) => $q->orderByPivot('sort'),
                'programCategory',
            ])
            ->orderBy('sort')
            ->get();

        $this->loadStartDate();
        $this->loadTarget();
        $this->clearCategoryComputedProperties();
        unset($this->weeks);
        unset($this->hasDefaultOverrides);
        unset($this->hasUserOverrides);
        unset($this->hasSelectedUserOverrides);
        unset($this->userOverrideCounts);
    }

    public function updated(string $property): void
    {
        if ($property === 'startDate') {
            $this->notifyDataChanged('startDate', [
                'userId' => $this->user,
                'startDate' => $this->startDate,
            ]);

            return;
        }

        if (in_array($property, ['measuredReps', 'measuredWeight', 'targetGoal'])) {
            unset($this->starting1RM);
            unset($this->target1RM);

            $this->notifyDataChanged('target', [
                'userId' => $this->user,
                'measuredReps' => $this->measuredReps,
                'measuredWeight' => $this->measuredWeight,
                'targetGoal' => $this->targetGoal,
            ]);
        }
    }

    public function render()
    {
        return view('livewire.training.view.plan');
    }
}
