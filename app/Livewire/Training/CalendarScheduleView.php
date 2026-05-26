<?php

namespace App\Livewire\Training;

use App\Data\Training\Calendar\CalendarSettingsData;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\UserGroup;
use App\Support\Training\WeekSlotModalPayloadBuilder;
use App\Training\CalendarDateService;
use App\Training\TrainingSessionMaterializer;
use App\Training\TrainingSessionRebuildService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        $this->dispatch('schedule-grid-refresh');
    }

    #[On('calendar-range-changed')]
    public function onCalendarRangeChanged(array $settings, int $weekStartsOn, int $weekEndsOn): void
    {
        $this->calendarSettings = CalendarSettingsData::from($settings);
        $this->weekStartsOn = $weekStartsOn;

        unset($this->weekGridData);
        $this->dispatch('schedule-grid-refresh');
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
        $today = Carbon::today();

        while ($current->lte($end)) {
            $weekStart = $current->copy();
            $days = [];
            $hasFutureDates = false;

            for ($d = 0; $d < 7; $d++) {
                $day = $weekStart->copy()->addDays($d);
                $isFutureDate = $day->gte($today);
                $hasFutureDates = $hasFutureDates || $isFutureDate;

                $days[] = [
                    'date' => $day->format('Y-m-d'),
                    'day' => $day->day,
                    'monthLabel' => $day->format('M'),
                    'isToday' => $day->isToday(),
                    'isFutureDate' => $isFutureDate,
                    'am' => [],
                    'pm' => [],
                ];
            }

            $weeks[] = [
                'key' => $current->isoWeekYear().'-W'.$current->isoWeek(),
                'label' => 'W'.$current->isoWeek(),
                'dateRange' => $weekStart->format('d M').' – '.$weekStart->copy()->addDays(6)->format('d M'),
                'hasFutureDates' => $hasFutureDates,
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
        $this->dispatch('schedule-grid-refresh');
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
        $this->dispatch('schedule-grid-refresh');
    }

    public function copyWeekSlots(string $sourceWeekStart, string $targetWeekStart): void
    {
        if ($sourceWeekStart === $targetWeekStart) {
            return;
        }

        $sourceStart = Carbon::parse($sourceWeekStart)->startOfDay();
        $targetStart = Carbon::parse($targetWeekStart)->startOfDay();
        $editableTargetStart = $this->editableWeekStart($targetStart);

        if ($editableTargetStart === null) {
            return;
        }

        $userIds = $this->scheduleScopeUserIds();

        if ($userIds === []) {
            return;
        }

        $sourceSlots = $this->scheduleScopeSlotsQuery($userIds)
            ->whereBetween('datetime', [$sourceStart->copy()->startOfDay(), $sourceStart->copy()->addDays(6)->endOfDay()])
            ->get();

        $targetEnd = $targetStart->copy()->addDays(6)->endOfDay();
        $targetRangeStart = $editableTargetStart->copy()->startOfDay();

        $deletedPairs = [];
        $createdPairs = [];

        DB::transaction(function () use ($userIds, $sourceSlots, $sourceStart, $targetStart, $targetRangeStart, $targetEnd, &$deletedPairs, &$createdPairs): void {
            $deletedPairs = $this->scheduleScopeSlotsQuery($userIds)
                ->whereBetween('datetime', [$targetRangeStart, $targetEnd])
                ->whereNull('completed_at')
                ->get(['training_program_id', 'user_id'])
                ->map(fn (TrainingProgramSlot $slot): string => $slot->training_program_id.'-'.$slot->user_id)
                ->values()
                ->all();

            $this->scheduleScopeSlotsQuery($userIds)
                ->whereBetween('datetime', [$targetRangeStart, $targetEnd])
                ->whereNull('completed_at')
                ->delete();

            $existingTargetKeys = $this->scheduleScopeSlotsQuery($userIds)
                ->whereBetween('datetime', [$targetRangeStart, $targetEnd])
                ->get(['training_program_id', 'user_id', 'datetime'])
                ->mapWithKeys(fn (TrainingProgramSlot $slot): array => [
                    $slot->training_program_id.'-'.$slot->user_id.'-'.$slot->datetime->format('Y-m-d H:i:s') => true,
                ])
                ->all();

            $materializer = app(TrainingSessionMaterializer::class);

            foreach ($sourceSlots as $slot) {
                $dayOffset = $sourceStart->diffInDays($slot->datetime->copy()->startOfDay());
                $targetDate = $targetStart->copy()->addDays($dayOffset);

                if ($targetDate->lt(Carbon::today())) {
                    continue;
                }

                $targetDateTime = $targetDate->format('Y-m-d').' '.$slot->datetime->format('H:i:s');
                $targetKey = $slot->training_program_id.'-'.$slot->user_id.'-'.$targetDateTime;

                if (isset($existingTargetKeys[$targetKey])) {
                    continue;
                }

                $clone = new TrainingProgramSlot([
                    'training_program_id' => $slot->training_program_id,
                    'user_id' => $slot->user_id,
                    'datetime' => $targetDateTime,
                ]);
                $clone->saveQuietly();
                $materializer->materialize($clone);

                $existingTargetKeys[$targetKey] = true;
                $createdPairs[] = $clone->training_program_id.'-'.$clone->user_id;
            }
        });

        $this->rebuildTouchedSchedulePairs(
            collect($deletedPairs)->merge($createdPairs)->unique()->values()->all(),
            $editableTargetStart->format('Y-m-d'),
        );

        unset($this->weekGridData);
        $this->dispatch('schedule-grid-refresh');
    }

    public function clearWeekSchedule(string $weekStart): void
    {
        $weekStartDate = Carbon::parse($weekStart)->startOfDay();
        $editableWeekStart = $this->editableWeekStart($weekStartDate);

        if ($editableWeekStart === null) {
            return;
        }

        $userIds = $this->scheduleScopeUserIds();

        if ($userIds === []) {
            return;
        }

        $targetEnd = $weekStartDate->copy()->addDays(6)->endOfDay();
        $targetRangeStart = $editableWeekStart->copy()->startOfDay();

        $deletedPairs = $this->scheduleScopeSlotsQuery($userIds)
            ->whereBetween('datetime', [$targetRangeStart, $targetEnd])
            ->whereNull('completed_at')
            ->get(['training_program_id', 'user_id'])
            ->map(fn (TrainingProgramSlot $slot): string => $slot->training_program_id.'-'.$slot->user_id)
            ->unique()
            ->values()
            ->all();

        $this->scheduleScopeSlotsQuery($userIds)
            ->whereBetween('datetime', [$targetRangeStart, $targetEnd])
            ->whereNull('completed_at')
            ->delete();

        $this->rebuildTouchedSchedulePairs($deletedPairs, $editableWeekStart->format('Y-m-d'));

        unset($this->weekGridData);
        $this->dispatch('schedule-grid-refresh');
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
        $this->dispatch('schedule-grid-refresh');
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
        $this->dispatch('schedule-grid-refresh');
    }

    public function render(): View
    {
        return view('livewire.training.calendar-schedule-view');
    }

    /**
     * @return int[]
     */
    protected function scheduleScopeUserIds(): array
    {
        if ($this->userId !== null) {
            return [$this->userId];
        }

        return UserGroup::query()
            ->find($this->groupId)
            ?->members()
            ->pluck('users.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all() ?? [];
    }

    protected function scheduleScopeSlotsQuery(array $userIds): \Illuminate\Database\Eloquent\Builder
    {
        return TrainingProgramSlot::query()
            ->whereIn('user_id', $userIds)
            ->whereNull('cancelled_at')
            ->whereHas('trainingProgram', fn ($query) => $query
                ->where('group_id', $this->groupId)
                ->whereNull('deleted_at'));
    }

    protected function editableWeekStart(Carbon $weekStart): ?Carbon
    {
        $today = Carbon::today();
        $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();

        if ($weekEnd->lt($today)) {
            return null;
        }

        return $weekStart->lt($today) ? $today->copy()->startOfDay() : $weekStart->copy()->startOfDay();
    }

    /**
     * @param  string[]  $pairs
     */
    protected function rebuildTouchedSchedulePairs(array $pairs, string $fromDate): void
    {
        $rebuildService = app(TrainingSessionRebuildService::class);

        foreach ($pairs as $pair) {
            [$trainingProgramId, $userId] = array_map('intval', explode('-', $pair, 2));

            if ($trainingProgramId <= 0 || $userId <= 0) {
                continue;
            }

            $rebuildService->rebuildFutureSlotsForTrainingProgramAthlete(
                $trainingProgramId,
                $userId,
                $fromDate,
            );
        }
    }
}
