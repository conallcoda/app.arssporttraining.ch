<?php

namespace App\Livewire\Training;

use App\Data\Training\Calendar\CalendarSettingsData;
use App\Data\Training\ExerciseProgramData;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExercisePlan;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramCategory;
use App\Models\Exercise\TrainingProgram;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Support\WeekOptions;
use Carbon\Carbon;
use Coda\Cms\Form\Form;
use Coda\Cms\Livewire\Concerns\InteractsWithFormData;
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
    use InteractsWithFormData {
        InteractsWithFormData::updated as traitUpdated;
    }

    #[Url]
    public string $mode = 'month';

    #[Url]
    public string $date = '';

    #[Url(except: '')]
    public string $start = '';

    #[Url(except: '')]
    public string $end = '';

    #[Url(except: '')]
    public string $group = '';

    #[Url(except: '')]
    public string $user = '';

    public array $data = [];

    public string $pendingMode = '';

    public string $pendingDate = '';

    public string $pendingStart = '';

    public string $pendingEnd = '';

    public bool $pendingOther = false;

    public ?string $otherMonthDate = null;

    public ?string $otherWeekDate = null;

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
            $this->date = Carbon::now()->format('Y-m-d');
        }

        $this->data = ['mode' => $this->mode];
        $this->syncPendingFromCurrent();
    }

    protected function syncPendingFromCurrent(): void
    {
        $this->pendingMode = $this->mode;
        $this->pendingDate = $this->date;
        $this->pendingStart = $this->start;
        $this->pendingEnd = $this->end;
    }

    public function updated(string $property, mixed $value): void
    {
        if ($property === 'data.mode') {
            $this->pendingMode = $value;
            $this->pendingOther = false;
        }

        $this->traitUpdated($property, $value);
    }

    public function openSettings(): void
    {
        unset($this->formConfig, $this->fieldsets);
        $this->syncPendingFromCurrent();
        $this->data = ['mode' => $this->mode];
        $this->pendingOther = false;
        $this->otherMonthDate = null;
        $this->otherWeekDate = null;
        Flux::modal('calendar-settings')->show();
    }

    public function toggleOther(): void
    {
        $this->pendingOther = ! $this->pendingOther;
    }

    public function applySettings(): void
    {
        $this->mode = $this->pendingMode;
        $this->date = $this->pendingDate;
        $this->start = $this->pendingStart;
        $this->end = $this->pendingEnd;

        unset($this->days, $this->weeks, $this->months, $this->title);
        unset($this->monthPresets, $this->weekPresets, $this->dayPresets, $this->rangePresets);

        Flux::modal('calendar-settings')->close();
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

        unset($this->selectionName, $this->programs);
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
    public function formConfig(): Form
    {
        return CalendarSettingsData::getForm();
    }

    #[Computed]
    public function fieldsets(): array
    {
        return $this->formConfig->resolveFieldsets($this->data);
    }

    #[Computed]
    public function title(): string
    {
        $date = Carbon::parse($this->date);

        return match ($this->mode) {
            'month' => $date->format('F Y'),
            'week' => 'W'.$date->isoWeek().' '.$date->isoWeekYear().' · '.$date->copy()->startOfWeek($this->weekStartsOn)->format('d M').' – '.$date->copy()->endOfWeek($this->weekEndsOn)->format('d M'),
            'day' => $date->format('d.m.Y'),
            'range' => $this->rangeTitle(),
            default => $date->format('F Y'),
        };
    }

    protected function rangeTitle(): string
    {
        $start = ($this->start ? Carbon::parse($this->start) : Carbon::parse($this->date))->startOfWeek($this->weekStartsOn);
        $end = ($this->end ? Carbon::parse($this->end) : $start->copy()->addMonth())->endOfWeek($this->weekEndsOn);

        return $start->format('d.m.Y').' – '.$end->format('d.m.Y');
    }

    #[Computed]
    public function programs(): Collection
    {
        if (! $this->hasSelection()) {
            return collect();
        }

        $query = TrainingProgram::with(['program.programCategory', 'sourcePlan']);

        if ($this->user !== '') {
            $query->forUser((int) $this->group, (int) $this->user);
        } else {
            $query->forGroup((int) $this->group);
        }

        return $query->orderBy('sort')->get();
    }

    #[Computed]
    public function days(): array
    {
        [$start, $end] = $this->dateRange();
        $days = [];
        $current = $start->copy();

        while ($current->lte($end)) {
            $days[] = [
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
        $date = Carbon::parse($this->date);

        return match ($this->mode) {
            'month' => [$date->copy()->startOfMonth()->startOfWeek($this->weekStartsOn), $date->copy()->endOfMonth()->endOfWeek($this->weekEndsOn)],
            'week' => [$date->copy()->startOfWeek($this->weekStartsOn), $date->copy()->endOfWeek($this->weekEndsOn)],
            'day' => [$date->copy(), $date->copy()],
            'range' => [
                ($this->start ? Carbon::parse($this->start) : $date->copy())->startOfWeek($this->weekStartsOn),
                ($this->end ? Carbon::parse($this->end) : $date->copy()->addMonth())->endOfWeek($this->weekEndsOn),
            ],
            default => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
        };
    }

    #[Computed]
    public function monthPresets(): array
    {
        $now = Carbon::now();
        $presets = [];

        for ($i = -6; $i <= 6; $i++) {
            $month = $now->copy()->addMonths($i);
            $presets[] = [
                'value' => $month->format('Y-m'),
                'label' => $month->format('M y'),
                'active' => Carbon::parse($this->pendingDate)->format('Y-m') === $month->format('Y-m'),
            ];
        }

        return $presets;
    }

    #[Computed]
    public function weekPresets(): array
    {
        $now = Carbon::now();
        $presets = [];

        for ($i = -6; $i <= 6; $i++) {
            $weekStart = $now->copy()->startOfWeek($this->weekStartsOn)->addWeeks($i);
            $presets[] = [
                'value' => $weekStart->format('Y-m-d'),
                'label' => 'W'.$weekStart->isoWeek(),
                'active' => Carbon::parse($this->pendingDate)->startOfWeek($this->weekStartsOn)->format('Y-m-d') === $weekStart->format('Y-m-d'),
            ];
        }

        return $presets;
    }

    #[Computed]
    public function dayPresets(): array
    {
        $now = Carbon::today();
        $selected = Carbon::parse($this->pendingDate)->startOfDay();
        $presets = [];

        for ($i = -7; $i <= 7; $i++) {
            $day = $now->copy()->addDays($i);
            $label = $day->format('D d');
            $suffix = match ($i) {
                -1 => ' (Yesterday)',
                0 => ' (Today)',
                1 => ' (Tomorrow)',
                default => '',
            };

            $presets[] = [
                'value' => $day->format('Y-m-d'),
                'label' => $label.$suffix,
                'active' => $selected->equalTo($day),
            ];
        }

        return $presets;
    }

    #[Computed]
    public function rangePresets(): array
    {
        $now = Carbon::now();
        $presets = [];

        for ($i = -3; $i <= 3; $i++) {
            $originalStart = $now->copy()->addMonths($i)->startOfMonth();
            $originalEnd = $now->copy()->addMonths($i + 1)->endOfMonth();
            $start = $originalStart->copy()->startOfWeek($this->weekStartsOn);
            $end = $originalEnd->copy()->endOfWeek($this->weekEndsOn);

            $presets[] = [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'label' => $originalStart->format('M').' – '.$originalEnd->format('M y'),
                'active' => $this->pendingStart === $start->format('Y-m-d') && $this->pendingEnd === $end->format('Y-m-d'),
            ];
        }

        return $presets;
    }

    public function selectMonth(string $yearMonth): void
    {
        $this->pendingOther = false;
        $this->otherMonthDate = null;
        $this->resetValidation();
        $date = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        $this->pendingDate = $date->format('Y-m-d');
    }

    public function selectWeek(string $monday): void
    {
        $this->pendingOther = false;
        $this->otherWeekDate = null;
        $this->resetValidation();
        $this->pendingDate = $monday;
    }

    public function selectDay(string $day): void
    {
        $this->pendingOther = false;
        $this->pendingDate = $day;
    }

    public function selectRange(string $start, string $end): void
    {
        $this->pendingOther = false;
        $this->pendingStart = Carbon::parse($start)->startOfWeek($this->weekStartsOn)->format('Y-m-d');
        $this->pendingEnd = Carbon::parse($end)->endOfWeek($this->weekEndsOn)->format('Y-m-d');
    }

    public function updatedOtherMonthDate(?string $value): void
    {
        if (! $value) {
            return;
        }

        try {
            $date = Carbon::parse($value);
        } catch (\Exception) {
            return;
        }

        $this->pendingDate = $date->startOfMonth()->format('Y-m-d');
        $this->otherMonthDate = null;
    }

    public function updatedOtherWeekDate(?string $value): void
    {
        if (! $value) {
            return;
        }

        try {
            $date = Carbon::parse($value);
        } catch (\Exception) {
            return;
        }

        $this->pendingDate = $date->startOfWeek($this->weekStartsOn)->format('Y-m-d');
        $this->otherWeekDate = null;
    }

    public function updatedPendingStart(): void
    {
        if ($this->pendingStart) {
            $this->pendingStart = Carbon::parse($this->pendingStart)->startOfWeek($this->weekStartsOn)->format('Y-m-d');
        }
    }

    public function updatedPendingEnd(): void
    {
        if ($this->pendingEnd) {
            $this->pendingEnd = Carbon::parse($this->pendingEnd)->endOfWeek($this->weekEndsOn)->format('Y-m-d');
        }
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
        TrainingProgram::findOrFail($trainingProgramId)->delete();
        unset($this->programs);
    }

    public function openEditProgram(int $trainingProgramId): void
    {
        $trainingProgram = TrainingProgram::with('program.programCategory', 'program.exercises')->findOrFail($trainingProgramId);
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
        TrainingProgram::findOrFail($this->editingTrainingProgramId)->delete();

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
