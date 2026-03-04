<?php

namespace App\Livewire\Training\View;

use App\Models\Exercise\ExerciseProgram;
use App\Models\Tag;
use App\Models\Users\User;
use App\Support\WeekOptions;
use App\Training\Reference\OneRepMaxConversion;
use Coda\Cms\Livewire\Concerns\InteractsWithParentView;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Plan extends Component
{
    use InteractsWithParentView;

    public Model $exercisePlan;

    public Collection $programs;

    public Collection $users;

    public ?int $selectedProgramId = null;

    // TODO: deprecated
    #[Url(except: null, as: 'user')]
    public int|string|null $user = null;

    // TODO: deprecated
    public ?string $startDate = null;

    // TODO: deprecated
    public ?int $selectedCategoryId = null;

    // TODO: deprecated
    public ?int $measuredReps = null;

    // TODO: deprecated
    public ?float $measuredWeight = null;

    // TODO: deprecated
    public int|float $targetGoal = 10;

    // TODO: deprecated
    public function updatingUser(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function mount(
        Model $exercisePlan,
        Collection $programs,
        Collection $users,
    ): void {
        $this->exercisePlan = $exercisePlan;
        $this->programs = $programs;
        $this->users = $users;
        $this->loadStartDate();
        $this->loadTarget();
        $this->initializeSelectedProgram();
    }

    protected function initializeSelectedProgram(): void
    {
        $programs = $this->scheduledPrograms;

        if ($programs->isNotEmpty() && $this->selectedProgramId === null) {
            $this->selectedProgramId = $programs->first()->id;
        }
    }

    #[Computed]
    public function scheduledPrograms(): Collection
    {
        $scheduledProgramIds = $this->programIdsFromSchedule;

        return $this->programs
            ->whereIn('id', $scheduledProgramIds)
            ->load(['exercises' => fn ($q) => $q->orderByPivot('sort'), 'exerciseCategory'])
            ->values();
    }

    #[Computed]
    public function selectedProgram(): ?ExerciseProgram
    {
        if ($this->selectedProgramId === null) {
            return null;
        }

        return $this->scheduledPrograms->firstWhere('id', $this->selectedProgramId);
    }

    public function selectProgram(int $programId): void
    {
        $this->selectedProgramId = $programId;
        unset($this->selectedProgram);
    }

    // TODO: deprecated
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
        $config = $this->exercisePlan->config;

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

    // TODO: deprecated
    #[Computed]
    public function weekOptions(): array
    {
        return WeekOptions::generate();
    }

    // TODO: deprecated
    #[Computed]
    public function programCategories(): Collection
    {
        $scheduledProgramIds = $this->programIdsFromSchedule;
        $scheduledPrograms = $this->programs->whereIn('id', $scheduledProgramIds);

        $categoryIds = $scheduledPrograms
            ->pluck('exercise_category_id')
            ->filter()
            ->unique()
            ->values();

        return Tag::whereIn('id', $categoryIds)
            ->orderBy('sort_order')
            ->get();
    }

    // TODO: deprecated
    #[Computed]
    public function programsForCategory(): Collection
    {
        if ($this->selectedCategoryId === null) {
            return new Collection;
        }

        $scheduledProgramIds = $this->programIdsFromSchedule;

        return $this->programs
            ->where('exercise_category_id', $this->selectedCategoryId)
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

    // TODO: deprecated
    #[Computed]
    public function starting1RM(): ?float
    {
        if ($this->measuredReps === null || $this->measuredWeight === null) {
            return null;
        }

        return OneRepMaxConversion::estimatedOneRepMax($this->measuredReps, $this->measuredWeight);
    }

    // TODO: deprecated
    #[Computed]
    public function target1RM(): ?float
    {
        $starting = $this->starting1RM;

        if ($starting === null) {
            return null;
        }

        return OneRepMaxConversion::targetOneRepMax($starting, $this->targetGoal);
    }

    // TODO: deprecated
    #[Computed]
    public function hasDefaultOverrides(): bool
    {
        return $this->exercisePlan->config->hasDefaultOverrides();
    }

    // TODO: deprecated
    #[Computed]
    public function hasUserOverrides(): bool
    {
        return $this->exercisePlan->config->hasUserOverrides();
    }

    // TODO: deprecated
    #[Computed]
    public function hasSelectedUserOverrides(): bool
    {
        if ($this->user === null) {
            return false;
        }

        return $this->exercisePlan->config->hasExerciseOverridesForUser($this->user);
    }

    // TODO: deprecated
    /** @return array<int, int> */
    #[Computed]
    public function userOverrideCounts(): array
    {
        $counts = [];

        foreach ($this->users as $userItem) {
            $count = $this->exercisePlan->config->exerciseOverrideCountForUser($userItem->id);
            if ($count > 0) {
                $counts[$userItem->id] = $count;
            }
        }

        return $counts;
    }

    // TODO: deprecated
    public function confirmResetDefaultSettings(): void
    {
        Flux::modal('reset-default-settings')->show();
    }

    // TODO: deprecated
    public function resetDefaultSettings(): void
    {
        $this->notifyDataChanged('resetDefaults', []);
        Flux::modal('reset-default-settings')->close();
        $this->loadStartDate();
        $this->loadTarget();
        $this->clearComputedProperties();
        unset($this->hasDefaultOverrides);
    }

    // TODO: deprecated
    public function confirmResetUserSettings(): void
    {
        Flux::modal('reset-user-settings')->show();
    }

    // TODO: deprecated
    public function resetUserSettings(): void
    {
        $this->notifyDataChanged('resetUserSettings', []);
        Flux::modal('reset-user-settings')->close();
        $this->loadStartDate();
        $this->loadTarget();
        $this->clearComputedProperties();
        unset($this->hasUserOverrides);
        unset($this->userOverrideCounts);
    }

    // TODO: deprecated
    public function confirmResetSelectedUserSettings(): void
    {
        Flux::modal('reset-selected-user-settings')->show();
    }

    // TODO: deprecated
    public function resetSelectedUserSettings(): void
    {
        $this->notifyDataChanged('resetSingleUserSettings', [
            'userId' => $this->user,
        ]);
        Flux::modal('reset-selected-user-settings')->close();
        $this->loadStartDate();
        $this->loadTarget();
        $this->clearComputedProperties();
        unset($this->hasSelectedUserOverrides);
        unset($this->userOverrideCounts);
        $this->dispatch('plan-overrides-reset');
    }

    // TODO: deprecated
    public function selectUser(?int $userId): void
    {
        $this->user = $userId;
        $this->loadStartDate();
        $this->loadTarget();
        $this->clearComputedProperties();
        $this->dispatch('plan-user-changed', userId: $userId);
    }

    // TODO: deprecated
    public function selectCategory(int $categoryId): void
    {
        $this->selectedCategoryId = $categoryId;
        unset($this->programsForCategory);
    }

    // TODO: deprecated
    protected function loadStartDate(): void
    {
        $config = $this->exercisePlan->config;

        $startDate = $this->user === null
            ? $config->defaultScheduleStartDate()
            : $config->userScheduleStartDate($this->user);

        $this->startDate = ! empty($startDate) ? $startDate : WeekOptions::getCurrentWeekValue();

        unset($this->scheduleWeeks);
        unset($this->programIdsFromSchedule);
    }

    // TODO: deprecated
    protected function loadTarget(): void
    {
        $config = $this->exercisePlan->config;

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

    protected function clearComputedProperties(): void
    {
        unset($this->scheduledPrograms);
        unset($this->selectedProgram);
        unset($this->sessionsPerWeekByProgram);
        unset($this->programCategories);
        unset($this->programsForCategory);
    }

    #[On('exercise-overrides-changed')]
    public function onExerciseOverridesChanged(): void
    {
        $this->exercisePlan->refresh();
        unset($this->hasDefaultOverrides);
        unset($this->hasUserOverrides);
        unset($this->hasSelectedUserOverrides);
        unset($this->userOverrideCounts);
    }

    #[On('parent-data-saved')]
    public function onParentDataSaved(): void
    {
        $this->exercisePlan->refresh();
        $ids = $this->exercisePlan->programIds();
        $this->programs = empty($ids)
            ? new Collection
            : ExerciseProgram::whereIn('id', $ids)
                ->with([
                    'exercises' => fn ($q) => $q->orderByPivot('sort'),
                    'exerciseCategory',
                ])
                ->orderBy('name')
                ->get();

        $this->loadStartDate();
        $this->loadTarget();
        $this->clearComputedProperties();
        unset($this->weeks);
        unset($this->hasDefaultOverrides);
        unset($this->hasUserOverrides);
        unset($this->hasSelectedUserOverrides);
        unset($this->userOverrideCounts);

        $this->initializeSelectedProgram();
    }

    // TODO: deprecated
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
