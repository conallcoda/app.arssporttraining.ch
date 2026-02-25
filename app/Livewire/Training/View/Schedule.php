<?php

namespace App\Livewire\Training\View;

use App\Data\Training\Config\Schedule\ScheduleWeek;
use App\Data\Training\TrainingProgramData;
use App\Form\Fields\Training\Program\Color;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanProgram;
use App\Models\Users\User;
use App\Support\WeekOptions;
use Coda\Cms\Form\Form;
use Coda\Cms\Livewire\Concerns\InteractsWithFormData;
use Coda\Cms\Livewire\Concerns\InteractsWithParentView;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as BaseCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Schedule extends Component
{
    use InteractsWithFormData;
    use InteractsWithParentView;

    public Model $trainingPlan;

    public Collection $programs;

    public Collection $users;

    #[Url(except: null, as: 'user')]
    public int|string|null $user = null;

    public ?string $startDate = null;

    public ?string $removingWeekId = null;

    public ?string $linkingWeekId = null;

    public ?string $linkToWeekId = null;

    public ?string $assigningWeekId = null;

    public ?int $assigningDay = null;

    public ?int $assigningSlot = null;

    public ?int $linkingProgramId = null;

    public ?int $editingProgramId = null;

    public string $planType = TrainingPlan::class;

    public array $data = [];

    public function mount(?int $trainingPlanId = null, ?string $planType = null): void
    {
        if ($planType !== null) {
            $this->planType = $planType;
        }

        if ($trainingPlanId !== null && ! isset($this->trainingPlan)) {
            $this->trainingPlan = $this->planType::findOrFail($trainingPlanId);
        }

        if (! isset($this->programs) || $this->programs->isEmpty()) {
            $this->loadPrograms();
        }

        if (! isset($this->users) || $this->users->isEmpty()) {
            $this->loadUsers();
        }

        $this->resetProgramForm();
        $this->loadStartDate();
    }

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
        $this->loadStartDate();
        unset($this->schedule);
        unset($this->hasCustomSchedule);
    }

    #[Computed]
    public function config()
    {
        return $this->trainingPlan->config;
    }

    #[Computed]
    public function hasCustomSchedule(): bool
    {
        if ($this->user === null) {
            return false;
        }

        return ! $this->config->isUserScheduleLocked($this->user);
    }

    /** @return BaseCollection<int, ScheduleWeek> */
    #[Computed]
    public function schedule(): BaseCollection
    {
        $weeks = ($this->user !== null && $this->hasCustomSchedule)
            ? $this->config->userScheduleWeeks($this->user)
            : $this->config->defaultScheduleWeeks();

        return collect($weeks)->map(fn (array $week, int $index) => ScheduleWeek::from([
            ...$week,
            'sort' => $week['sort'] ?? $index,
        ]));
    }

    #[Computed]
    public function weekOptions(): array
    {
        return WeekOptions::generate();
    }

    #[Computed]
    public function programOptions(): array
    {
        return $this->programs->mapWithKeys(fn ($program) => [
            $program->id => $program->name,
        ])->all();
    }

    public function availableProgramOptionsForSlot(): array
    {
        if ($this->assigningWeekId === null || $this->assigningDay === null || $this->assigningSlot === null) {
            return $this->programOptions;
        }

        $week = $this->schedule->firstWhere('id', $this->assigningWeekId);
        if (! $week) {
            return $this->programOptions;
        }

        $resolvedSlots = $this->getResolvedSlots($week);
        $existingProgramIds = $resolvedSlots[$this->assigningDay][$this->assigningSlot]['programs'] ?? [];

        return collect($this->programOptions)
            ->reject(fn ($name, $id) => in_array($id, $existingProgramIds, true))
            ->all();
    }

    #[Computed]
    public function availableWeeksForLinking(): array
    {
        return $this->schedule
            ->filter(fn (ScheduleWeek $week) => $week->linkedTo === null && $week->id !== $this->linkingWeekId)
            ->map(fn (ScheduleWeek $week, int $index) => [
                'id' => $week->id,
                'label' => 'Week '.($index + 1),
            ])
            ->values()
            ->all();
    }

    #[Computed]
    public function formConfig(): Form
    {
        return TrainingProgramData::getForm();
    }

    #[Computed]
    public function fields(): array
    {
        return $this->formConfig->getFields();
    }

    #[Computed]
    public function fieldsets(): array
    {
        return $this->formConfig->resolveFieldsets($this->data);
    }

    public function getResolvedSlots(ScheduleWeek $week): array
    {
        if ($week->linkedTo === null) {
            return $week->grid();
        }

        $sourceWeek = $this->schedule->firstWhere('id', $week->linkedTo);

        return $sourceWeek ? $this->getResolvedSlots($sourceWeek) : $week->grid();
    }

    public function getProgramColor(?int $programId): string
    {
        if ($programId === null) {
            return Color::DEFAULT_COLOR;
        }

        $program = $this->programs->firstWhere('id', $programId);

        return $program?->programCategory?->color ?? Color::DEFAULT_COLOR;
    }

    public function confirmResetSchedule(): void
    {
        Flux::modal('reset-schedule')->show();
    }

    public function resetSchedule(): void
    {
        $this->onScheduleEvent('lock-schedule', []);
        Flux::modal('reset-schedule')->close();
    }

    public function openRemoveModal(string $weekId): void
    {
        $this->removingWeekId = $weekId;
        Flux::modal('remove-week')->show();
    }

    public function confirmRemoveWeek(): void
    {
        if ($this->removingWeekId === null) {
            return;
        }

        $this->onScheduleEvent('remove-week', ['weekId' => $this->removingWeekId]);
        $this->removingWeekId = null;
        Flux::modal('remove-week')->close();
    }

    public function openLinkModal(string $weekId): void
    {
        $this->linkingWeekId = $weekId;
        $week = $this->schedule->firstWhere('id', $weekId);
        $this->linkToWeekId = $week?->linkedTo;
        unset($this->availableWeeksForLinking);
        Flux::modal('link-week')->show();
    }

    public function confirmLinkWeek(): void
    {
        if ($this->linkingWeekId === null) {
            return;
        }

        $linkToWeekId = $this->linkToWeekId ?: null;
        $this->onScheduleEvent('link-week', [
            'weekId' => $this->linkingWeekId,
            'linkToWeekId' => $linkToWeekId,
        ]);
        $this->linkingWeekId = null;
        $this->linkToWeekId = null;
        Flux::modal('link-week')->close();
    }

    public function openCreateProgramModal(string $weekId, int $day, int $slot): void
    {
        $this->resetProgramForm();
        $this->assigningWeekId = $weekId;
        $this->assigningDay = $day;
        $this->assigningSlot = $slot;
        Flux::modal('add-program')->show();
    }

    public function openEditProgramModal(int $programId, string $weekId, int $day, int $slot): void
    {
        $week = $this->schedule->firstWhere('id', $weekId);
        if ($week && $week->linkedTo !== null) {
            return;
        }

        $program = $this->programs->firstWhere('id', $programId);
        if (! $program) {
            return;
        }

        $this->editingProgramId = $programId;
        $this->assigningWeekId = $weekId;
        $this->assigningDay = $day;
        $this->assigningSlot = $slot;

        $this->data = [
            'name' => $program->name,
            'program_category_id' => $program->program_category_id,
            'exercises' => $program->exercises->map(fn ($exercise) => [
                'id' => $exercise->id,
                '_key' => uniqid('item_', true),
                'sort' => $exercise->pivot->sort ?? 0,
            ])->values()->all(),
        ];

        Flux::modal('add-program')->show();
    }

    public function saveProgram(): void
    {
        $this->validate($this->buildValidationRulesFromFieldsets());

        $this->notifyDataChanged('program', [
            'data' => $this->data,
            'editingProgramId' => $this->editingProgramId,
            'assigningWeekId' => $this->assigningWeekId,
            'assigningDay' => $this->assigningDay,
            'assigningSlot' => $this->assigningSlot,
            'userId' => $this->user,
        ]);

        $this->resetProgramForm();
        Flux::modal('add-program')->close();
    }

    public function openLinkProgramModal(string $weekId, int $day, int $slot): void
    {
        $this->assigningWeekId = $weekId;
        $this->assigningDay = $day;
        $this->assigningSlot = $slot;
        $this->linkingProgramId = array_key_first($this->availableProgramOptionsForSlot());
        Flux::modal('link-program')->show();
    }

    public function confirmLinkProgram(): void
    {
        if ($this->assigningWeekId === null || $this->assigningDay === null || $this->assigningSlot === null || $this->linkingProgramId === null) {
            return;
        }

        $this->onScheduleEvent('assign-program', [
            'weekId' => $this->assigningWeekId,
            'day' => $this->assigningDay,
            'slot' => $this->assigningSlot,
            'programId' => $this->linkingProgramId,
        ]);
        $this->resetProgramForm();
        Flux::modal('link-program')->close();
    }

    public function removeFromSchedule(): void
    {
        $programId = $this->editingProgramId ?? $this->linkingProgramId;

        if ($programId === null || $this->assigningWeekId === null || $this->assigningDay === null || $this->assigningSlot === null) {
            return;
        }

        $this->onScheduleEvent('unassign-program', [
            'weekId' => $this->assigningWeekId,
            'day' => $this->assigningDay,
            'slot' => $this->assigningSlot,
            'programId' => $programId,
        ]);

        if ($this->editingProgramId !== null) {
            Flux::modal('add-program')->close();
        } else {
            Flux::modal('link-program')->close();
        }

        $this->resetProgramForm();
    }

    public function resetProgramForm(): void
    {
        $this->editingProgramId = null;
        $this->assigningWeekId = null;
        $this->assigningDay = null;
        $this->assigningSlot = null;
        $this->linkingProgramId = null;
        $this->data = $this->buildDefaultsFromFieldsets();
    }

    public function updatedStartDate(): void
    {
        $this->notifyDataChanged('startDate', [
            'userId' => $this->user,
            'startDate' => $this->startDate,
        ]);
    }

    #[On('schedule-event')]
    public function onScheduleEvent(string $type, array $data = []): void
    {
        $this->notifyDataChanged('schedule', [
            'type' => $type,
            'data' => $data,
            'userId' => $this->user,
        ]);
    }

    protected function loadPrograms(): void
    {
        $this->programs = TrainingPlanProgram::query()
            ->where('plannable_type', get_class($this->trainingPlan))
            ->where('plannable_id', $this->trainingPlan->id)
            ->with([
                'exercises' => fn ($q) => $q->orderByPivot('sort'),
                'programCategory',
            ])
            ->orderBy('sort')
            ->get();
    }

    protected function loadUsers(): void
    {
        if ($this->trainingPlan->isTemplate()) {
            return;
        }

        $this->users = $this->trainingPlan->allUsers()
            ->orderBy('forename')
            ->orderBy('surname')
            ->get();
    }

    #[On('parent-data-saved')]
    public function onParentDataSaved(): void
    {
        $this->trainingPlan->refresh();
        $this->loadPrograms();
        unset($this->schedule);
        unset($this->hasCustomSchedule);
        unset($this->config);
        unset($this->programOptions);
    }

    protected function loadStartDate(): void
    {
        $config = $this->trainingPlan->config;

        $startDate = $this->user === null
            ? $config->defaultScheduleStartDate()
            : $config->userScheduleStartDate($this->user);

        $this->startDate = ! empty($startDate) ? $startDate : WeekOptions::getCurrentWeekValue();
    }

    public function render()
    {
        return view('livewire.training.view.schedule');
    }
}
