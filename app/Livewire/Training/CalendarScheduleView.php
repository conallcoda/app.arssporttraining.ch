<?php

namespace App\Livewire\Training;

use App\Data\Training\Calendar\CalendarSettingsData;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\UserGroup;
use App\Support\Training\WeekSlotModalPayloadBuilder;
use App\Training\CalendarDateService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class CalendarScheduleView extends Component
{
    public int $groupId;

    public ?int $userId = null;

    public CalendarSettingsData $calendarSettings;

    public int $weekStartsOn;

    public string $weekEditMode = 'view';

    public ?int $quickProgramId = null;

    public array $quickSelectedAthletes = [];

    #[On('calendar-selection-changed')]
    public function onSelectionChanged(int $groupId, ?int $userId = null): void
    {
        $this->groupId = $groupId;
        $this->userId = $userId;

        unset($this->weekGridData, $this->quickProgramOptions, $this->quickAthleteOptions);
    }

    #[On('calendar-range-changed')]
    public function onCalendarRangeChanged(array $settings, int $weekStartsOn, int $weekEndsOn): void
    {
        $this->calendarSettings = CalendarSettingsData::from($settings);
        $this->weekStartsOn = $weekStartsOn;

        unset($this->weekGridData);
    }

    /** @return array{Carbon, Carbon} */
    protected function dateRange(): array
    {
        $weekEndsOn = ($this->weekStartsOn + 6) % 7;

        return app(CalendarDateService::class)->dateRange($this->calendarSettings, $this->weekStartsOn, $weekEndsOn);
    }

    #[Computed]
    public function weekGridData(): array
    {
        [$start, $end] = $this->dateRange();

        return $this->buildWeekGridSkeleton($start, $end);
    }

    protected function buildWeekGridSkeleton(Carbon $start, Carbon $end): array
    {
        $weeks = [];
        $current = $start->copy()->startOfWeek($this->weekStartsOn);

        while ($current->lte($end)) {
            $weekStart = $current->copy();
            $days = [];

            for ($d = 0; $d < 7; $d++) {
                $day = $weekStart->copy()->addDays($d);

                $days[] = [
                    'date' => $day->format('Y-m-d'),
                    'day' => $day->day,
                    'monthLabel' => $day->format('M'),
                    'isToday' => $day->isToday(),
                    'am' => [],
                    'pm' => [],
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

    public function updatedWeekEditMode(): void
    {
        if ($this->weekEditMode === 'edit') {
            $this->quickSelectedAthletes = array_map('strval', $this->quickAthleteOptions->pluck('id')->all());
        } else {
            $this->quickProgramId = null;
            $this->quickSelectedAthletes = [];
        }
    }

    #[Computed]
    public function quickProgramOptions(): array
    {
        return TrainingProgram::query()
            ->with('program')
            ->where('group_id', $this->groupId)
            ->orderBy('sort')
            ->get()
            ->pluck('program.name', 'id')
            ->all();
    }

    #[Computed]
    public function quickAthleteOptions(): Collection
    {
        if ($this->userId !== null) {
            return collect();
        }

        $group = UserGroup::with('members')->find($this->groupId);

        if ($group === null) {
            return collect();
        }

        return $group->members->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
        ]);
    }

    public function updatedQuickProgramId(): void
    {
        $this->resetErrorBag('quickProgramId');
    }

    public function updatedQuickSelectedAthletes(): void
    {
        $this->resetErrorBag('quickSelectedAthletes');
    }

    public function quickCreateWeekSlot(string $date, string $period): void
    {
        $hasErrors = false;

        if ($this->quickProgramId === null) {
            $this->addError('quickProgramId', __('Select a program.'));
            $hasErrors = true;
        }

        if ($this->quickAthleteOptions->isNotEmpty() && empty($this->quickSelectedAthletes)) {
            $this->addError('quickSelectedAthletes', __('Select athletes.'));
            $hasErrors = true;
        }

        if ($hasErrors) {
            return;
        }

        $startTime = $period === 'pm' ? '14:00' : '09:00';
        $datetime = $date.' '.$startTime.':00';
        $trainingProgramId = $this->quickProgramId;

        if ($this->userId !== null) {
            TrainingProgramSlot::firstOrCreate([
                'training_program_id' => $trainingProgramId,
                'user_id' => $this->userId,
                'datetime' => $datetime,
            ]);
        } else {
            $selectedMembers = array_map('intval', $this->quickSelectedAthletes);
            foreach ($selectedMembers as $memberId) {
                TrainingProgramSlot::firstOrCreate([
                    'training_program_id' => $trainingProgramId,
                    'user_id' => $memberId,
                    'datetime' => $datetime,
                ]);
            }
        }

        unset($this->weekGridData);
    }

    public function quickRemoveWeekSlot(int $trainingProgramId, string $date, string $startTime): void
    {
        $datetime = $date.' '.$startTime.':00';

        if ($this->userId !== null) {
            TrainingProgramSlot::query()
                ->where('training_program_id', $trainingProgramId)
                ->where('user_id', $this->userId)
                ->where('datetime', $datetime)
                ->whereNull('completed_at')
                ->delete();
        } else {
            $group = UserGroup::with('members')->find($this->groupId);
            if ($group !== null) {
                TrainingProgramSlot::query()
                    ->where('training_program_id', $trainingProgramId)
                    ->whereIn('user_id', $group->members->pluck('id'))
                    ->where('datetime', $datetime)
                    ->whereNull('completed_at')
                    ->delete();
            }
        }

        unset($this->weekGridData);
    }

    public function openWeekSlot(string $date, string $period): void
    {
        $builder = app(WeekSlotModalPayloadBuilder::class);
        $payload = $builder->forCreate(
            $date,
            $builder->defaultStartTime($period),
            $this->groupId,
            $this->userId,
        );

        $this->dispatch('open-week-slot', data: $payload);
    }

    public function editWeekSlot(int $trainingProgramId, string $date, string $startTime): void
    {
        $payload = app(WeekSlotModalPayloadBuilder::class)->forEdit(
            $trainingProgramId,
            $date,
            $startTime,
            $this->groupId,
            $this->userId,
        );

        $this->dispatch('open-week-slot', data: $payload);
    }

    #[On('week-slot.submitted')]
    public function onWeekSlotSubmitted(array $data): void
    {
        $trainingProgramId = (int) $data['training_program_id'];
        $datetime = $data['date'].' '.$data['start_time'].':00';

        $originalProgramId = $data['original_training_program_id'] ?? null;
        $originalStartTime = $data['original_start_time'] ?? null;
        $originalDatetime = $originalStartTime !== null ? $data['date'].' '.$originalStartTime.':00' : null;

        $programChanged = $originalProgramId !== null && (int) $originalProgramId !== $trainingProgramId;
        $timeChanged = $originalDatetime !== null && $originalDatetime !== $datetime;

        $selectedMembers = $data['selected_members'] ?? [];
        $deselectedMembers = $data['deselected_members'] ?? [];

        if (empty($selectedMembers) && empty($deselectedMembers) && $this->userId !== null) {
            if ($programChanged || $timeChanged) {
                TrainingProgramSlot::query()
                    ->where('training_program_id', (int) $originalProgramId)
                    ->where('user_id', $this->userId)
                    ->where('datetime', $originalDatetime)
                    ->delete();
            }

            TrainingProgramSlot::firstOrCreate([
                'training_program_id' => $trainingProgramId,
                'user_id' => $this->userId,
                'datetime' => $datetime,
            ]);
        } else {
            $allMembers = array_merge($selectedMembers, $deselectedMembers);

            if ($programChanged || $timeChanged) {
                TrainingProgramSlot::query()
                    ->where('training_program_id', (int) $originalProgramId)
                    ->whereIn('user_id', $allMembers)
                    ->where('datetime', $originalDatetime)
                    ->delete();
            }

            foreach ($selectedMembers as $memberId) {
                TrainingProgramSlot::firstOrCreate([
                    'training_program_id' => $trainingProgramId,
                    'user_id' => $memberId,
                    'datetime' => $datetime,
                ]);
            }

            if (! empty($deselectedMembers)) {
                TrainingProgramSlot::query()
                    ->where('training_program_id', $trainingProgramId)
                    ->whereIn('user_id', $deselectedMembers)
                    ->where('datetime', $datetime)
                    ->delete();
            }
        }

        unset($this->weekGridData);
    }

    #[On('week-slot.deleted')]
    public function onWeekSlotDeleted(array $data): void
    {
        $trainingProgramId = (int) $data['training_program_id'];
        $datetime = $data['date'].' '.$data['start_time'].':00';

        if ($this->userId !== null) {
            TrainingProgramSlot::query()
                ->where('training_program_id', $trainingProgramId)
                ->where('user_id', $this->userId)
                ->where('datetime', $datetime)
                ->whereNull('completed_at')
                ->delete();
        } else {
            $group = UserGroup::with('members')->find($this->groupId);
            if ($group !== null) {
                TrainingProgramSlot::query()
                    ->where('training_program_id', $trainingProgramId)
                    ->whereIn('user_id', $group->members->pluck('id'))
                    ->where('datetime', $datetime)
                    ->whereNull('completed_at')
                    ->delete();
            }
        }

        unset($this->weekGridData);
    }

    public function render(): View
    {
        return view('livewire.training.calendar-schedule-view');
    }
}
