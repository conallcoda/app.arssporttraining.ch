<?php

namespace App\Livewire\Training;

use App\Data\Training\Calendar\CalendarSettingsData;
use App\Data\Training\ExerciseProgramData;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExercisePlan;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramCategory;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Support\WeekOptions;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.database')]
#[Title('ARS - Athlete Training // Calendar')]
class CalendarIndex extends Component
{
    #[Url]
    public string $period = 'month';

    #[Url]
    public string $date = '';

    #[Url(except: '')]
    public string $start = '';

    #[Url(except: '')]
    public string $end = '';

    #[Url(except: 'program')]
    public string $viewMode = 'program';

    #[Url(except: '')]
    public string $group = '';

    #[Url(except: '')]
    public string $user = '';

    public CalendarSettingsData $calendarSettings;

    public int $weekStartsOn;

    public int $weekEndsOn;

    public string $addContentSearch = '';

    public string $addContentTab = 'plan';

    public ?int $addExerciseCategoryId = null;

    public ?int $editingTrainingProgramId = null;

    public function mount(): void
    {
        $this->weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);
        $this->weekEndsOn = ($this->weekStartsOn + 6) % 7;

        if ($this->date === '') {
            $this->date = Carbon::now()->startOfMonth()->format('Y-m-d');
        }

        $this->calendarSettings = new CalendarSettingsData(
            period: $this->period,
            date: $this->date,
            start: $this->start ?: null,
            end: $this->end ?: null,
        );
    }

    public function updatedViewMode(): void
    {
        unset($this->weekGridData);
    }

    public function openCalendarRange(): void
    {
        $this->dispatch('open-calendar-range', data: $this->calendarSettings->toArray());
    }

    #[On('calendar-range.submitted')]
    public function onCalendarRangeSubmitted(array $data): void
    {
        $this->calendarSettings = CalendarSettingsData::from($data);

        $this->period = $this->calendarSettings->period;
        $this->date = $this->calendarSettings->date ?? $this->date;
        $this->start = $this->calendarSettings->start ?? '';
        $this->end = $this->calendarSettings->end ?? '';

        unset($this->days, $this->weeks, $this->months, $this->title, $this->weekGridData);
    }

    #[On('sidebar-selection-changed')]
    public function onSidebarSelectionChanged(array $selected): void
    {
        $this->group = '';
        $this->user = '';

        if (count($selected) === 0) {
            return;
        }

        $first = $selected[0];
        $this->group = (string) $first['group'];

        if (isset($first['user'])) {
            $this->user = (string) $first['user'];
        }

        unset($this->selectionName, $this->programs, $this->slotMap, $this->userOverrides, $this->overrideSlotMap, $this->slotState, $this->weekGridData);
    }

    #[Computed]
    public function selectionName(): ?string
    {
        if ($this->user !== '') {
            return User::find((int) $this->user)?->name;
        }

        if ($this->group !== '') {
            return UserGroup::find((int) $this->group)?->name;
        }

        return null;
    }

    public function hasSelection(): bool
    {
        return $this->group !== '' || $this->user !== '';
    }

    #[Computed]
    public function title(): string
    {
        $date = Carbon::parse($this->calendarSettings->date);

        return match ($this->calendarSettings->period) {
            'month' => $date->format('F Y'),
            'week' => 'W'.$date->isoWeek().' '.$date->isoWeekYear().' · '.$date->copy()->startOfWeek($this->weekStartsOn)->format('d M').' – '.$date->copy()->endOfWeek($this->weekEndsOn)->format('d M'),
            'day' => $date->format('d.m.Y'),
            'range' => $this->rangeTitle(),
            default => $date->format('F Y'),
        };
    }

    protected function rangeTitle(): string
    {
        $date = $this->calendarSettings->date;
        $start = ($this->calendarSettings->start ? Carbon::parse($this->calendarSettings->start) : Carbon::parse($date))->startOfWeek($this->weekStartsOn);
        $end = ($this->calendarSettings->end ? Carbon::parse($this->calendarSettings->end) : $start->copy()->addMonth())->endOfWeek($this->weekEndsOn);

        return $start->format('d.m.Y').' – '.$end->format('d.m.Y');
    }

    #[Computed]
    public function programs(): Collection
    {
        if (! $this->hasSelection()) {
            return collect();
        }

        [$start, $end] = $this->dateRange();

        $eagerLoads = [
            'program.programCategory',
            'program.exercises',
            'sourcePlan',
            'slots' => fn ($q) => $q->withTrashed()->whereBetween('datetime', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]),
        ];

        $groupPrograms = TrainingProgram::with($eagerLoads)
            ->forGroup((int) $this->group)
            ->orderBy('sort')
            ->get();

        if ($this->user === '') {
            return $groupPrograms;
        }

        $groupExerciseProgramIds = $groupPrograms->pluck('exercise_program_id')->toArray();

        $independentUserPrograms = TrainingProgram::with($eagerLoads)
            ->forUser((int) $this->group, (int) $this->user)
            ->whereNotIn('exercise_program_id', $groupExerciseProgramIds)
            ->orderBy('sort')
            ->get();

        return $groupPrograms->concat($independentUserPrograms);
    }

    #[Computed]
    public function userOverrides(): Collection
    {
        if ($this->user === '' || ! $this->hasSelection()) {
            return collect();
        }

        [$start, $end] = $this->dateRange();

        $groupExerciseProgramIds = $this->programs
            ->filter(fn (TrainingProgram $p) => $p->isGroupLevel())
            ->pluck('exercise_program_id')
            ->toArray();

        if (empty($groupExerciseProgramIds)) {
            return collect();
        }

        return TrainingProgram::with([
            'slots' => fn ($q) => $q->whereBetween('datetime', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]),
        ])
            ->forUser((int) $this->group, (int) $this->user)
            ->whereIn('exercise_program_id', $groupExerciseProgramIds)
            ->get()
            ->keyBy('exercise_program_id');
    }

    #[Computed]
    public function overrideSlotMap(): array
    {
        $map = [];

        foreach ($this->userOverrides as $override) {
            foreach ($override->slots as $slot) {
                $key = $override->exercise_program_id.'-'.$slot->datetime->format('Y-m-d H:i:s');
                $map[$key] = $slot->active;
            }
        }

        return $map;
    }

    #[Computed]
    public function days(): array
    {
        [$start, $end] = $this->dateRange();
        $days = [];
        $current = $start->copy();

        while ($current->lte($end)) {
            $days[] = [
                'date' => $current->format('Y-m-d'),
                'day' => $current->day,
                'label' => $current->format('D'),
                'isToday' => $current->isToday(),
                'oddWeek' => $current->isoWeek() % 2 !== 0,
                'monthLabel' => $current->format('M'),
            ];
            $current->addDay();
        }

        return $days;
    }

    #[Computed]
    public function weeks(): array
    {
        [$start, $end] = $this->dateRange();

        return WeekOptions::weekSpansForDateRange($start, $end);
    }

    #[Computed]
    public function months(): array
    {
        [$start, $end] = $this->dateRange();
        $months = [];
        $current = $start->copy()->startOfDay();
        $endDate = $end->copy()->startOfDay();
        $currentMonth = null;

        while ($current->lte($endDate)) {
            $key = $current->format('Y-m');

            if ($currentMonth !== null && $currentMonth['key'] === $key) {
                $currentMonth['colspan']++;
            } else {
                if ($currentMonth !== null) {
                    $months[] = $currentMonth;
                }
                $currentMonth = [
                    'key' => $key,
                    'label' => $current->format('F Y'),
                    'colspan' => 1,
                ];
            }

            $current->addDay();
        }

        if ($currentMonth !== null) {
            $months[] = $currentMonth;
        }

        return $months;
    }

    /** @return array{Carbon, Carbon} */
    protected function dateRange(): array
    {
        $date = Carbon::parse($this->calendarSettings->date);

        return match ($this->calendarSettings->period) {
            'month' => [$date->copy()->startOfMonth()->startOfWeek($this->weekStartsOn), $date->copy()->endOfMonth()->endOfWeek($this->weekEndsOn)],
            'week' => [$date->copy()->startOfWeek($this->weekStartsOn), $date->copy()->endOfWeek($this->weekEndsOn)],
            'day' => [$date->copy(), $date->copy()],
            'range' => [
                ($this->calendarSettings->start ? Carbon::parse($this->calendarSettings->start) : $date->copy())->startOfWeek($this->weekStartsOn),
                ($this->calendarSettings->end ? Carbon::parse($this->calendarSettings->end) : $date->copy()->addMonth())->endOfWeek($this->weekEndsOn),
            ],
            default => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
        };
    }

    #[Computed]
    public function slotMap(): array
    {
        $map = [];
        $isUserView = $this->user !== '';
        $overrides = $isUserView ? $this->overrideSlotMap : [];

        foreach ($this->programs as $program) {
            foreach ($program->slots as $slot) {
                $key = $program->id.'-'.$slot->datetime->format('Y-m-d H:i:s');
                $active = ! $slot->trashed();

                if ($isUserView && $program->isGroupLevel()) {
                    $overrideKey = $program->exercise_program_id.'-'.$slot->datetime->format('Y-m-d H:i:s');
                    if (isset($overrides[$overrideKey])) {
                        $active = (bool) $overrides[$overrideKey];
                    }
                }

                $map[$key] = $active;
            }

            if ($isUserView && $program->isGroupLevel()) {
                foreach ($overrides as $overrideKey => $overrideActive) {
                    if (! str_starts_with($overrideKey, $program->exercise_program_id.'-')) {
                        continue;
                    }

                    $suffix = substr($overrideKey, strlen($program->exercise_program_id.'-'));
                    $mapKey = $program->id.'-'.$suffix;

                    if (! isset($map[$mapKey]) && $overrideActive) {
                        $map[$mapKey] = true;
                    }
                }
            }
        }

        return $map;
    }

    #[Computed]
    public function slotState(): array
    {
        if ($this->user === '') {
            return [];
        }

        $states = [];
        $overrides = $this->overrideSlotMap;

        foreach ($this->programs as $program) {
            if (! $program->isGroupLevel()) {
                continue;
            }

            foreach ($this->days as $day) {
                foreach (['09:00:00', '14:00:00'] as $time) {
                    $dt = $day['date'].' '.$time;
                    $key = $program->id.'-'.$dt;
                    $overrideKey = $program->exercise_program_id.'-'.$dt;

                    if (isset($overrides[$overrideKey])) {
                        $states[$key] = 'overridden';
                    } else {
                        $states[$key] = 'inherited';
                    }
                }
            }
        }

        return $states;
    }

    #[Computed]
    public function weekGridData(): array
    {
        [$start, $end] = $this->dateRange();
        $programs = $this->programs;
        $slotMap = $this->slotMap;
        $weeks = [];
        $current = $start->copy()->startOfWeek($this->weekStartsOn);

        while ($current->lte($end)) {
            $weekStart = $current->copy();
            $days = [];

            for ($d = 0; $d < 7; $d++) {
                $day = $weekStart->copy()->addDays($d);
                $dateStr = $day->format('Y-m-d');
                $amPrograms = [];
                $pmPrograms = [];

                foreach ($programs as $program) {
                    $amKey = $program->id.'-'.$dateStr.' 09:00:00';
                    $pmKey = $program->id.'-'.$dateStr.' 14:00:00';
                    $amActive = $slotMap[$amKey] ?? false;
                    $pmActive = $slotMap[$pmKey] ?? false;

                    if ($amActive) {
                        $amPrograms[] = [
                            'trainingProgramId' => $program->id,
                            'name' => $program->program->name,
                            'color' => $program->program->programCategory?->color,
                            'time' => '09:00',
                        ];
                    }

                    if ($pmActive) {
                        $pmPrograms[] = [
                            'trainingProgramId' => $program->id,
                            'name' => $program->program->name,
                            'color' => $program->program->programCategory?->color,
                            'time' => '14:00',
                        ];
                    }
                }

                $days[] = [
                    'date' => $dateStr,
                    'day' => $day->day,
                    'monthLabel' => $day->format('M'),
                    'isToday' => $day->isToday(),
                    'am' => $amPrograms,
                    'pm' => $pmPrograms,
                ];
            }

            $weeks[] = [
                'key' => $current->isoWeekYear().'-W'.$current->isoWeek(),
                'label' => 'W'.$current->isoWeek(),
                'dateRange' => $weekStart->format('d M').' – '.$weekStart->copy()->addDays(6)->format('d M'),
                'days' => $days,
            ];

            $current->addWeek();
        }

        return $weeks;
    }

    public function toggleSlot(int $trainingProgramId, string $datetime): void
    {
        $program = TrainingProgram::findOrFail($trainingProgramId);

        if ($this->user !== '' && $program->isGroupLevel()) {
            $this->toggleOverrideSlot($program, $datetime);
        } else {
            $this->toggleDirectSlot($trainingProgramId, $datetime);
        }

        unset($this->programs, $this->slotMap, $this->userOverrides, $this->overrideSlotMap, $this->slotState, $this->weekGridData);
    }

    protected function toggleDirectSlot(int $trainingProgramId, string $datetime): void
    {
        $existing = TrainingProgramSlot::withTrashed()
            ->where('training_program_id', $trainingProgramId)
            ->where('datetime', $datetime)
            ->first();

        if ($existing === null) {
            TrainingProgramSlot::create([
                'training_program_id' => $trainingProgramId,
                'datetime' => $datetime,
            ]);
        } elseif ($existing->trashed()) {
            $existing->restore();
        } else {
            $existing->delete();
        }
    }

    protected function toggleOverrideSlot(TrainingProgram $groupProgram, string $datetime): void
    {
        $overrideProgram = TrainingProgram::findOrCreateOverride($groupProgram, (int) $this->user);

        $existingOverride = TrainingProgramSlot::query()
            ->where('training_program_id', $overrideProgram->id)
            ->where('datetime', $datetime)
            ->first();

        if ($existingOverride !== null) {
            $existingOverride->forceDelete();

            if ($overrideProgram->slots()->count() === 0) {
                $overrideProgram->forceDelete();
            }

            return;
        }

        $groupSlotActive = TrainingProgramSlot::query()
            ->where('training_program_id', $groupProgram->id)
            ->where('datetime', $datetime)
            ->exists();

        TrainingProgramSlot::create([
            'training_program_id' => $overrideProgram->id,
            'datetime' => $datetime,
            'active' => ! $groupSlotActive,
        ]);
    }

    public function openWeekSlot(string $date, string $period): void
    {
        $startTime = $period === 'pm' ? '14:00' : '09:00';

        $this->dispatch('open-week-slot', data: [
            'date' => $date,
            'start_time' => $startTime,
            'groupId' => $this->group !== '' ? (int) $this->group : null,
            'userId' => $this->user !== '' ? (int) $this->user : null,
        ]);
    }

    public function editWeekSlot(int $trainingProgramId, string $date, string $startTime): void
    {
        $this->dispatch('open-week-slot', data: [
            'date' => $date,
            'start_time' => $startTime,
            'training_program_id' => $trainingProgramId,
            'groupId' => $this->group !== '' ? (int) $this->group : null,
            'userId' => $this->user !== '' ? (int) $this->user : null,
        ]);
    }

    #[On('week-slot.submitted')]
    public function onWeekSlotSubmitted(array $data): void
    {
        $trainingProgramId = (int) $data['training_program_id'];
        $datetime = $data['date'].' '.$data['start_time'].':00';

        $existing = TrainingProgramSlot::withTrashed()
            ->where('training_program_id', $trainingProgramId)
            ->where('datetime', $datetime)
            ->first();

        if ($existing === null) {
            TrainingProgramSlot::create([
                'training_program_id' => $trainingProgramId,
                'datetime' => $datetime,
            ]);
        } elseif ($existing->trashed()) {
            $existing->restore();
        }

        unset($this->programs, $this->slotMap, $this->userOverrides, $this->overrideSlotMap, $this->slotState, $this->weekGridData);
    }

    #[On('week-slot.deleted')]
    public function onWeekSlotDeleted(array $data): void
    {
        $trainingProgramId = (int) $data['training_program_id'];
        $datetime = $data['date'].' '.$data['start_time'].':00';

        $slot = TrainingProgramSlot::withTrashed()
            ->where('training_program_id', $trainingProgramId)
            ->where('datetime', $datetime)
            ->first();

        if ($slot !== null) {
            $slot->forceDelete();
        }

        unset($this->programs, $this->slotMap, $this->userOverrides, $this->overrideSlotMap, $this->slotState, $this->weekGridData);
    }

    public function openAddContent(): void
    {
        $this->addContentSearch = '';
        $this->addContentTab = 'plan';
        $this->addExerciseCategoryId = null;
        Flux::modal('add-content')->show();
    }

    public function updatedAddContentTab(): void
    {
        $this->addContentSearch = '';
        $this->addExerciseCategoryId = null;
        unset($this->addContentOptions);
    }

    #[Computed]
    public function categoryOptions(): array
    {
        return ExerciseProgramCategory::query()
            ->orderBy('sort')
            ->pluck('name', 'id')
            ->all();
    }

    public function updatedAddContentSearch(): void
    {
        unset($this->addContentOptions);
    }

    #[Computed]
    public function addContentOptions(): Collection
    {
        $search = trim($this->addContentSearch);

        return match ($this->addContentTab) {
            'plan' => ExercisePlan::query()
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name']),
            'program' => ExerciseProgram::query()
                ->with('programCategory:id,name,color')
                ->whereNull('owner_id')
                ->whereNull('owner_type')
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'program_category_id']),
            'exercise' => Exercise::query()
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name']),
            default => collect(),
        };
    }

    public function addFromPlan(int $planId): void
    {
        $plan = ExercisePlan::findOrFail($planId);
        $groupId = (int) $this->group;
        $userId = $this->user !== '' ? (int) $this->user : null;

        TrainingProgram::importFromPlan($plan, $groupId, $userId);

        unset($this->programs);
        Flux::modal('add-content')->close();
    }

    public function addFromProgram(int $programId): void
    {
        $program = ExerciseProgram::findOrFail($programId);
        $groupId = (int) $this->group;
        $userId = $this->user !== '' ? (int) $this->user : null;

        TrainingProgram::importProgram($program, $groupId, $userId);

        unset($this->programs);
        Flux::modal('add-content')->close();
    }

    public function addFromExercise(int $exerciseId): void
    {
        $this->validate([
            'addExerciseCategoryId' => 'required|integer|exists:exercise_program_categories,id',
        ]);

        $exercise = Exercise::findOrFail($exerciseId);
        $groupId = (int) $this->group;
        $userId = $this->user !== '' ? (int) $this->user : null;

        TrainingProgram::importExercise($exercise, $groupId, $userId, categoryId: $this->addExerciseCategoryId);

        $this->addExerciseCategoryId = null;
        unset($this->programs);
        Flux::modal('add-content')->close();
    }

    public function removeTrainingProgram(int $trainingProgramId): void
    {
        $program = TrainingProgram::findOrFail($trainingProgramId);

        if ($this->user !== '' && $program->isGroupLevel()) {
            return;
        }

        $program->delete();
        unset($this->programs);
    }

    public function openEditProgram(int $trainingProgramId): void
    {
        $trainingProgram = TrainingProgram::with('program.programCategory', 'program.exercises')->findOrFail($trainingProgramId);

        if ($this->user !== '' && $trainingProgram->isGroupLevel()) {
            return;
        }

        $programData = ExerciseProgramData::fromModel($trainingProgram->program);

        $this->editingTrainingProgramId = $trainingProgramId;

        $this->dispatch('open-edit-program', data: $programData->toArray());
    }

    #[On('edit-program.submitted')]
    public function handleEditProgramSubmitted(array $data): void
    {
        $trainingProgram = TrainingProgram::findOrFail($this->editingTrainingProgramId);

        $programData = ExerciseProgramData::from([
            'id' => $trainingProgram->exercise_program_id,
            ...$data,
        ]);
        $programData->persist();

        $this->editingTrainingProgramId = null;
        unset($this->programs);
    }

    #[On('edit-program.delete-requested')]
    public function handleEditProgramDeleteRequested(): void
    {
        Flux::modal('confirm-delete-program')->show();
    }

    public function deleteEditingTrainingProgram(): void
    {
        $program = TrainingProgram::findOrFail($this->editingTrainingProgramId);

        if ($this->user !== '' && $program->isGroupLevel()) {
            return;
        }

        $program->delete();

        $this->editingTrainingProgramId = null;
        unset($this->programs);
        Flux::modal('confirm-delete-program')->close();
        Flux::modal('edit-program')->close();
    }

    public function render()
    {
        return view('livewire.training.calendar-index');
    }
}
