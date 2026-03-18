<?php

namespace App\Livewire\Training;

use App\Data\Exercise\ExerciseSetting;
use App\Data\Training\Calendar\CalendarSettingsData;
use App\Data\Training\Config\EffectiveExerciseConfig;
use App\Data\Training\Config\ExerciseOverrides;
use App\Data\Training\ExerciseProgramData;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramNote;
use App\Models\Training\TrainingProgramNoteTypeEnum;
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

#[Layout('components.layouts.admin')]
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

    #[Url(except: 'programs')]
    public string $viewMode = 'programs';

    #[Url(except: '')]
    public string $group = '';

    #[Url(except: '')]
    public string $user = '';

    public CalendarSettingsData $calendarSettings;

    public int $weekStartsOn;

    public int $weekEndsOn;

    public string $addContentSearch = '';

    public string $addContentTab = 'program';

    public ?int $editingTrainingProgramId = null;

    public string $weekEditMode = 'view';

    public ?int $quickProgramId = null;

    public array $quickSelectedAthletes = [];

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

        unset($this->days, $this->weeks, $this->months, $this->title, $this->weekGridData, $this->overviewData, $this->allNotes);
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

        unset($this->selectionName, $this->programs, $this->groupedPrograms, $this->slotMap, $this->programCellSlots, $this->weekGridData, $this->allNotes);

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
    public function overviewData(): array
    {
        [$start, $end] = $this->dateRange();

        $slots = TrainingProgramSlot::query()
            ->join('training_programs', 'training_program_slots.training_program_id', '=', 'training_programs.id')
            ->join('exercise_programs', 'training_programs.exercise_program_id', '=', 'exercise_programs.id')
            ->leftJoin('tags', 'exercise_programs.exercise_category_id', '=', 'tags.id')
            ->whereNull('training_programs.deleted_at')
            ->whereBetween('training_program_slots.datetime', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->selectRaw('training_programs.group_id, training_program_slots.user_id, DATE(training_program_slots.datetime) as slot_date, TIME(training_program_slots.datetime) as slot_time, exercise_programs.name as program_name, tags.color as category_color')
            ->get();

        $groupDates = [];
        $userSlots = [];

        foreach ($slots as $slot) {
            $groupId = $slot->group_id;
            $date = $slot->slot_date;

            $groupDates[$groupId][$date] = true;

            $userSlots[$groupId][$slot->user_id][$date][] = [
                'name' => $slot->program_name,
                'color' => $slot->category_color,
                'time' => substr($slot->slot_time, 0, 5),
            ];
        }

        $groups = UserGroup::with('members')->orderBy('name')->get();
        $result = [];

        foreach ($groups as $group) {
            $gid = $group->id;
            $members = [];

            foreach ($group->members as $member) {
                $members[] = [
                    'user' => $member,
                    'dates' => $userSlots[$gid][$member->id] ?? [],
                ];
            }

            $result[] = [
                'group' => $group,
                'dates' => $groupDates[$gid] ?? [],
                'members' => $members,
            ];
        }

        return $result;
    }

    #[Computed]
    public function groupedPrograms(): Collection
    {
        return $this->programs->groupBy(
            fn (TrainingProgram $entry) => $entry->program->exercise_category_id ?? 0
        )->map(function (Collection $entries, int $categoryId) {
            return [
                'category' => $categoryId > 0 ? $entries->first()->program->exerciseCategory : null,
                'entries' => $entries,
            ];
        });
    }

    #[Computed]
    public function programs(): Collection
    {
        if (! $this->hasSelection()) {
            return collect();
        }

        return TrainingProgram::with([
            'program.exerciseCategory',
            'program.exercises',
        ])
            ->where('group_id', (int) $this->group)
            ->orderBy('sort')
            ->get();
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
        [$start, $end] = $this->dateRange();
        $programIds = $this->programs->pluck('id');

        $query = TrainingProgramSlot::query()
            ->whereIn('training_program_id', $programIds)
            ->whereBetween('datetime', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);

        if ($this->user !== '') {
            $query->where('user_id', (int) $this->user);
        }

        $map = [];
        foreach ($query->get() as $slot) {
            $key = $slot->training_program_id.'-'.$slot->datetime->format('Y-m-d H:i:s');
            $map[$key] = true;
        }

        return $map;
    }

    #[Computed]
    public function programCellSlots(): array
    {
        [$start, $end] = $this->dateRange();
        $programIds = $this->programs->pluck('id');

        if ($programIds->isEmpty()) {
            return [];
        }

        $query = TrainingProgramSlot::query()
            ->whereIn('training_program_id', $programIds)
            ->whereBetween('datetime', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->join('users', 'training_program_slots.user_id', '=', 'users.id')
            ->select('training_program_id', 'datetime', 'users.forename', 'users.surname');

        if ($this->user !== '') {
            $query->where('training_program_slots.user_id', (int) $this->user);
        }

        $map = [];

        foreach ($query->get() as $row) {
            $dateKey = $row->training_program_id.'-'.$row->datetime->format('Y-m-d');
            $time = $row->datetime->format('H:i');
            $name = trim("{$row->forename} {$row->surname}");

            if (! isset($map[$dateKey])) {
                $map[$dateKey] = [];
            }

            if (! isset($map[$dateKey][$time])) {
                $map[$dateKey][$time] = [];
            }

            if (! in_array($name, $map[$dateKey][$time], true)) {
                $map[$dateKey][$time][] = $name;
            }
        }

        foreach ($map as &$times) {
            ksort($times);
        }

        return $map;
    }

    #[Computed]
    public function allNotes(): array
    {
        $days = $this->days;
        $totalDays = count($days);
        $empty = ['notes' => [], 'laneCount' => 0, 'totalDays' => $totalDays];

        if (! $this->hasSelection()) {
            return $empty;
        }

        [$start, $end] = $this->dateRange();
        $groupId = (int) $this->group;

        $dayIndex = [];
        foreach ($days as $i => $day) {
            $dayIndex[$day['date']] = $i;
        }

        $query = TrainingProgramNote::query()
            ->where('group_id', $groupId)
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($q2) use ($start, $end) {
                    $q2->where('start', '<=', $end->format('Y-m-d'))
                        ->where(function ($q3) use ($start) {
                            $q3->where('end', '>=', $start->format('Y-m-d'))
                                ->orWhereNull('end');
                        });
                })->orWhereBetween('start', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
            });

        if ($this->user !== '') {
            $query->where('user_id', (int) $this->user);
        }

        $notes = $query->get();

        $grouped = $notes->groupBy(fn ($n) => $n->type->value.'|'.$n->start->format('Y-m-d').'|'.($n->end?->format('Y-m-d') ?? '').'|'.$n->note);

        $noteRanges = [];

        foreach ($grouped as $group) {
            $representative = $group->first();
            $noteStart = $representative->start->format('Y-m-d');
            $noteEnd = ($representative->end ?? $representative->start)->format('Y-m-d');

            $clampedStart = max($noteStart, $start->format('Y-m-d'));
            $clampedEnd = min($noteEnd, $end->format('Y-m-d'));

            if (! isset($dayIndex[$clampedStart])) {
                continue;
            }

            $startIdx = $dayIndex[$clampedStart];
            $endIdx = $dayIndex[$clampedEnd] ?? ($totalDays - 1);
            $colspan = $endIdx - $startIdx + 1;

            $noteRanges[] = [
                'id' => $representative->id,
                'note' => $representative->note,
                'color' => $representative->color,
                'type' => $representative->type->value,
                'startIdx' => $startIdx,
                'endIdx' => $endIdx,
                'colspan' => $colspan,
            ];
        }

        $typeOrder = array_flip(array_map(fn ($case) => $case->value, TrainingProgramNoteTypeEnum::cases()));
        usort($noteRanges, fn ($a, $b) => ($typeOrder[$a['type']] ?? 0) <=> ($typeOrder[$b['type']] ?? 0) ?: $a['startIdx'] <=> $b['startIdx']);

        $laneEnds = [];
        foreach ($noteRanges as &$noteRange) {
            $placed = false;
            foreach ($laneEnds as $lane => $laneEnd) {
                if ($laneEnd < $noteRange['startIdx']) {
                    $noteRange['lane'] = $lane;
                    $laneEnds[$lane] = $noteRange['endIdx'];
                    $placed = true;
                    break;
                }
            }
            if (! $placed) {
                $noteRange['lane'] = count($laneEnds);
                $laneEnds[] = $noteRange['endIdx'];
            }
        }
        unset($noteRange);

        return [
            'notes' => $noteRanges,
            'laneCount' => count($laneEnds),
            'totalDays' => $totalDays,
        ];
    }

    #[Computed]
    public function weekGridData(): array
    {
        [$start, $end] = $this->dateRange();

        if ($this->group !== '' && $this->user === '') {
            return $this->buildAthleteWeekGrid($start, $end);
        }

        return $this->buildProgramWeekGrid($start, $end);
    }

    protected function buildProgramWeekGrid(Carbon $start, Carbon $end): array
    {
        $programs = $this->programs;
        $slotMap = $this->slotMap;
        $userName = $this->selectionName;
        $weeks = [];
        $current = $start->copy()->startOfWeek($this->weekStartsOn);

        $slotsByProgramDate = [];
        foreach ($slotMap as $key => $active) {
            $datetime = substr($key, -19);
            $programId = substr($key, 0, strlen($key) - 20);
            $date = substr($datetime, 0, 10);
            $time = substr($datetime, 11, 5);
            $slotsByProgramDate[$programId.'-'.$date][] = $time;
        }

        while ($current->lte($end)) {
            $weekStart = $current->copy();
            $days = [];

            for ($d = 0; $d < 7; $d++) {
                $day = $weekStart->copy()->addDays($d);
                $dateStr = $day->format('Y-m-d');
                $amPrograms = [];
                $pmPrograms = [];

                foreach ($programs as $program) {
                    $times = $slotsByProgramDate[$program->id.'-'.$dateStr] ?? [];

                    foreach ($times as $time) {
                        $entry = [
                            'trainingProgramId' => $program->id,
                            'name' => $program->program->name,
                            'color' => $program->program->exerciseCategory?->color,
                            'time' => $time,
                            'userNames' => [$userName],
                        ];

                        if ($time < '12:00') {
                            $amPrograms[] = $entry;
                        } else {
                            $pmPrograms[] = $entry;
                        }
                    }
                }

                usort($amPrograms, fn ($a, $b) => $a['time'] <=> $b['time']);
                usort($pmPrograms, fn ($a, $b) => $a['time'] <=> $b['time']);

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

    protected function buildAthleteWeekGrid(Carbon $start, Carbon $end): array
    {
        $groupId = (int) $this->group;

        $slots = TrainingProgramSlot::query()
            ->join('training_programs', 'training_program_slots.training_program_id', '=', 'training_programs.id')
            ->join('exercise_programs', 'training_programs.exercise_program_id', '=', 'exercise_programs.id')
            ->leftJoin('tags', 'exercise_programs.exercise_category_id', '=', 'tags.id')
            ->join('users', 'training_program_slots.user_id', '=', 'users.id')
            ->whereNull('training_programs.deleted_at')
            ->where('training_programs.group_id', $groupId)
            ->whereBetween('training_program_slots.datetime', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->selectRaw("training_programs.id as training_program_id, training_programs.exercise_program_id, DATE(training_program_slots.datetime) as slot_date, TIME(training_program_slots.datetime) as slot_time, exercise_programs.name as program_name, tags.color as category_color, TRIM(CONCAT(users.forename, ' ', users.surname)) as user_name")
            ->get();

        $rawSlotsByDate = [];

        foreach ($slots as $slot) {
            $date = $slot->slot_date;
            $time = substr($slot->slot_time, 0, 5);
            $key = $slot->exercise_program_id.'-'.$time;

            $rawSlotsByDate[$date][$key]['trainingProgramId'] = $slot->training_program_id;
            $rawSlotsByDate[$date][$key]['name'] = $slot->program_name;
            $rawSlotsByDate[$date][$key]['color'] = $slot->category_color;
            $rawSlotsByDate[$date][$key]['time'] = $time;
            $rawSlotsByDate[$date][$key]['userNames'][] = $slot->user_name;
        }

        $weeks = [];
        $current = $start->copy()->startOfWeek($this->weekStartsOn);

        while ($current->lte($end)) {
            $weekStart = $current->copy();
            $days = [];

            for ($d = 0; $d < 7; $d++) {
                $day = $weekStart->copy()->addDays($d);
                $dateStr = $day->format('Y-m-d');
                $dayEntries = array_values($rawSlotsByDate[$dateStr] ?? []);

                $am = array_filter($dayEntries, fn ($e) => $e['time'] < '12:00');
                $pm = array_filter($dayEntries, fn ($e) => $e['time'] >= '12:00');

                usort($am, fn ($a, $b) => $a['time'] <=> $b['time'] ?: $a['name'] <=> $b['name']);
                usort($pm, fn ($a, $b) => $a['time'] <=> $b['time'] ?: $a['name'] <=> $b['name']);

                $days[] = [
                    'date' => $dateStr,
                    'day' => $day->day,
                    'monthLabel' => $day->format('M'),
                    'isToday' => $day->isToday(),
                    'am' => array_values($am),
                    'pm' => array_values($pm),
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

    public function selectFromOverview(int $groupId, ?int $userId = null): void
    {
        $this->group = (string) $groupId;
        $this->user = $userId !== null ? (string) $userId : '';

        $selected = ['group' => $groupId];
        if ($userId !== null) {
            $selected['user'] = $userId;
        }

        $this->dispatch('overview-selection', selected: [$selected]);

        unset(
            $this->selectionName,
            $this->programs,
            $this->groupedPrograms,
            $this->slotMap,
            $this->programCellSlots,
            $this->weekGridData,
            $this->overviewData,
        );

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
        $groupId = $this->group !== '' ? (int) $this->group : null;

        if ($groupId === null) {
            return [];
        }

        return TrainingProgram::query()
            ->with('program')
            ->where('group_id', $groupId)
            ->orderBy('sort')
            ->get()
            ->pluck('program.name', 'id')
            ->all();
    }

    #[Computed]
    public function quickAthleteOptions(): Collection
    {
        $groupId = $this->group !== '' ? (int) $this->group : null;
        $userId = $this->user !== '' ? (int) $this->user : null;

        if ($groupId === null || $userId !== null) {
            return collect();
        }

        $group = UserGroup::with('members')->find($groupId);

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

        if ($this->user !== '') {
            TrainingProgramSlot::firstOrCreate([
                'training_program_id' => $trainingProgramId,
                'user_id' => (int) $this->user,
                'datetime' => $datetime,
            ]);
        } else {
            $selectedMembers = array_map('intval', $this->quickSelectedAthletes);
            foreach ($selectedMembers as $userId) {
                TrainingProgramSlot::firstOrCreate([
                    'training_program_id' => $trainingProgramId,
                    'user_id' => $userId,
                    'datetime' => $datetime,
                ]);
            }
        }

        unset($this->programs, $this->groupedPrograms, $this->slotMap, $this->programCellSlots, $this->weekGridData);
    }

    public function quickRemoveWeekSlot(int $trainingProgramId, string $date, string $startTime): void
    {
        $datetime = $date.' '.$startTime.':00';

        if ($this->user !== '') {
            TrainingProgramSlot::query()
                ->where('training_program_id', $trainingProgramId)
                ->where('user_id', (int) $this->user)
                ->where('datetime', $datetime)
                ->delete();
        } else {
            $group = UserGroup::with('members')->find((int) $this->group);
            if ($group !== null) {
                TrainingProgramSlot::query()
                    ->where('training_program_id', $trainingProgramId)
                    ->whereIn('user_id', $group->members->pluck('id'))
                    ->where('datetime', $datetime)
                    ->delete();
            }
        }

        unset($this->programs, $this->groupedPrograms, $this->slotMap, $this->programCellSlots, $this->weekGridData);
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

    public function openProgramSlot(int $trainingProgramId, string $date): void
    {
        $this->dispatch('open-week-slot', data: [
            'date' => $date,
            'start_time' => '09:00',
            'training_program_id' => $trainingProgramId,
            'groupId' => $this->group !== '' ? (int) $this->group : null,
            'userId' => null,
            'prefill' => true,
            'preselectedUserId' => $this->user !== '' ? (int) $this->user : null,
        ]);
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

        if (empty($selectedMembers) && empty($deselectedMembers) && $this->user !== '') {
            if ($programChanged || $timeChanged) {
                TrainingProgramSlot::query()
                    ->where('training_program_id', (int) $originalProgramId)
                    ->where('user_id', (int) $this->user)
                    ->where('datetime', $originalDatetime)
                    ->delete();
            }

            TrainingProgramSlot::firstOrCreate([
                'training_program_id' => $trainingProgramId,
                'user_id' => (int) $this->user,
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

            foreach ($selectedMembers as $userId) {
                TrainingProgramSlot::firstOrCreate([
                    'training_program_id' => $trainingProgramId,
                    'user_id' => $userId,
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

        unset($this->programs, $this->groupedPrograms, $this->slotMap, $this->programCellSlots, $this->weekGridData);

    }

    #[On('week-slot.deleted')]
    public function onWeekSlotDeleted(array $data): void
    {
        $trainingProgramId = (int) $data['training_program_id'];
        $datetime = $data['date'].' '.$data['start_time'].':00';

        if ($this->user !== '') {
            TrainingProgramSlot::query()
                ->where('training_program_id', $trainingProgramId)
                ->where('user_id', (int) $this->user)
                ->where('datetime', $datetime)
                ->delete();
        } else {
            $group = UserGroup::with('members')->find((int) $this->group);
            if ($group !== null) {
                TrainingProgramSlot::query()
                    ->where('training_program_id', $trainingProgramId)
                    ->whereIn('user_id', $group->members->pluck('id'))
                    ->where('datetime', $datetime)
                    ->delete();
            }
        }

        unset($this->programs, $this->groupedPrograms, $this->slotMap, $this->programCellSlots, $this->weekGridData);
    }

    public function openFocusNote(string $date): void
    {
        $this->dispatch('open-focus-note', data: [
            'date' => $date,
            'groupId' => $this->group !== '' ? (int) $this->group : null,
            'userId' => $this->user !== '' ? (int) $this->user : null,
        ]);
    }

    public function editFocusNote(int $noteId): void
    {
        $this->dispatch('open-focus-note', data: [
            'noteId' => $noteId,
            'groupId' => $this->group !== '' ? (int) $this->group : null,
            'userId' => $this->user !== '' ? (int) $this->user : null,
        ]);
    }

    #[On('focus-note.submitted')]
    public function onFocusNoteSubmitted(array $data): void
    {
        $groupId = $data['groupId'];
        $editingNoteId = $data['editing_note_id'] ?? null;
        $selectedMembers = $data['selected_members'] ?? [];
        $userId = $data['userId'] ?? null;
        $type = TrainingProgramNoteTypeEnum::from($data['type'] ?? 'focus');
        $color = $data['color'] ?? 'amber';

        if ($editingNoteId !== null) {
            if ($userId !== null && empty($selectedMembers)) {
                TrainingProgramNote::destroy($editingNoteId);
            } else {
                $existingNote = TrainingProgramNote::find($editingNoteId);
                if ($existingNote) {
                    TrainingProgramNote::query()
                        ->where('group_id', $groupId)
                        ->where('type', $existingNote->type)
                        ->where('start', $existingNote->start)
                        ->where('note', $existingNote->note)
                        ->delete();
                }
            }
        }

        if ($userId !== null && empty($selectedMembers)) {
            TrainingProgramNote::create([
                'group_id' => $groupId,
                'user_id' => $userId,
                'type' => $type,
                'start' => $data['start'],
                'end' => $data['end'] ?: null,
                'note' => $data['note'],
                'color' => $color,
            ]);
        } else {
            foreach ($selectedMembers as $memberId) {
                TrainingProgramNote::create([
                    'group_id' => $groupId,
                    'user_id' => $memberId,
                    'type' => $type,
                    'start' => $data['start'],
                    'end' => $data['end'] ?: null,
                    'note' => $data['note'],
                    'color' => $color,
                ]);
            }
        }

        unset($this->allNotes);
    }

    #[On('focus-note.deleted')]
    public function onFocusNoteDeleted(array $data): void
    {
        $editingNoteId = $data['editing_note_id'] ?? null;
        $groupId = $data['groupId'];
        $userId = $data['userId'] ?? null;

        if ($editingNoteId === null) {
            return;
        }

        if ($userId !== null) {
            TrainingProgramNote::destroy($editingNoteId);
        } else {
            $existingNote = TrainingProgramNote::find($editingNoteId);
            if ($existingNote) {
                TrainingProgramNote::query()
                    ->where('group_id', $groupId)
                    ->where('type', $existingNote->type)
                    ->where('start', $existingNote->start)
                    ->where('note', $existingNote->note)
                    ->delete();
            }
        }

        unset($this->allNotes);
    }

    public function openExerciseSettings(int $exerciseId, int $exerciseProgramId, int $trainingProgramId): void
    {
        $exercise = Exercise::findOrFail($exerciseId);
        $exerciseProgram = ExerciseProgram::findOrFail($exerciseProgramId);

        $base = $exercise->config;
        $planOverrides = $exerciseProgram->config->defaultExerciseOverrides($exerciseId);
        $userId = $this->user !== '' ? (int) $this->user : null;
        $userOverrides = $userId !== null
            ? $exerciseProgram->config->userExerciseOverrides($userId, $exerciseId)
            : null;
        $effectiveConfig = EffectiveExerciseConfig::resolve($base, $planOverrides, $userOverrides);

        [$start, $end] = $this->dateRange();

        $slotQuery = TrainingProgramSlot::query()
            ->where('training_program_id', $trainingProgramId)
            ->whereBetween('datetime', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);

        if ($this->user !== '') {
            $slotQuery->where('user_id', (int) $this->user);
        }

        $slotDates = $slotQuery->pluck('datetime')
            ->map(fn ($dt) => Carbon::parse($dt)->format('Y-m-d'))
            ->unique()
            ->sort()
            ->values();

        $scheduledWeeks = [];
        foreach ($slotDates as $date) {
            $isoWeek = Carbon::parse($date)->isoWeek();
            if (! in_array($isoWeek, $scheduledWeeks, true)) {
                $scheduledWeeks[] = $isoWeek;
            }
        }

        $weeks = count($scheduledWeeks);
        $scheduled = $weeks > 0;

        if (! $scheduled) {
            $weeks = 1;
            $scheduledWeeks = [Carbon::now()->isoWeek()];
        }

        $weekLabels = [];
        foreach ($scheduledWeeks as $i => $isoWeek) {
            $weekLabels[$i] = __('W:week', ['week' => $isoWeek]);
        }

        $weekSessions = [];
        $sessionsPerWeek = 1;
        if ($scheduled) {
            $weekSlotCounts = $slotDates->groupBy(fn ($date) => Carbon::parse($date)->isoWeek())
                ->map->count();
            $sessionsPerWeek = max(1, (int) $weekSlotCounts->max());

            foreach ($scheduledWeeks as $i => $isoWeek) {
                $weekSessions[$i] = (int) ($weekSlotCounts[$isoWeek] ?? 1);
            }
        }

        $this->dispatch('open-calendar-exercise-settings', data: [
            'exerciseId' => $exerciseId,
            'exerciseProgramId' => $exerciseProgramId,
            'exerciseName' => $exercise->name,
            'config' => $effectiveConfig,
            'weeks' => $weeks,
            'sessionsPerWeek' => $sessionsPerWeek,
            'weekLabels' => $weekLabels,
            'weekSessions' => $weekSessions,
            'scheduled' => $scheduled,
            'userId' => $userId,
        ]);
    }

    #[On('calendar-exercise-settings.saved')]
    public function onCalendarExerciseSettingsSaved(array $data): void
    {
        $exerciseId = (int) $data['exerciseId'];
        $exerciseProgramId = (int) $data['exerciseProgramId'];
        $settingsConfig = $data['config'] ?? [];
        $userId = isset($data['userId']) ? (int) $data['userId'] : null;

        $exercise = Exercise::findOrFail($exerciseId);
        $exerciseProgram = ExerciseProgram::findOrFail($exerciseProgramId);
        $config = $exerciseProgram->config;

        if ($userId !== null) {
            $planOverrides = $config->defaultExerciseOverrides($exerciseId);
            $parentConfig = EffectiveExerciseConfig::resolve($exercise->config, $planOverrides);
            $overrides = $config->userExerciseOverrides($userId, $exerciseId);
        } else {
            $parentConfig = $exercise->config->toArray();
            $overrides = $config->defaultExerciseOverrides($exerciseId);
        }

        $overrides = $this->buildOverridesFromDiff($overrides, $settingsConfig, $parentConfig);

        if ($userId !== null) {
            $config->setUserExerciseOverrides($userId, $exerciseId, $overrides);
        } else {
            $config->setDefaultExerciseOverrides($exerciseId, $overrides);
        }

        $exerciseProgram->config = $config;
        $exerciseProgram->save();
    }

    protected function buildOverridesFromDiff(ExerciseOverrides $overrides, array $settingsConfig, array $parentConfig): ExerciseOverrides
    {
        $overrides->settings = ($settingsConfig['settings'] ?? null) == ($parentConfig['settings'] ?? null)
            ? null
            : ($settingsConfig['settings'] ?? null);

        $formSets = $settingsConfig['sets'] ?? null;
        $parentSets = $parentConfig['sets'] ?? null;
        if (is_array($formSets) && is_array($parentSets)) {
            $formSets = array_merge($parentSets, $formSets);
        }
        $overrides->sets = $formSets == $parentSets
            ? null
            : \App\Data\Exercise\Settings\SetsSetting::from($formSets);

        $settingKeys = ['reps', 'weight', 'tempo', 'rest', 'distance', 'duration', 'heartRate', 'heartRateZone', 'pace', 'watts'];

        foreach ($settingKeys as $key) {
            $formValue = $settingsConfig[$key] ?? null;
            $parentValue = $parentConfig[$key] ?? null;

            if (is_array($formValue) && is_array($parentValue)) {
                $formValue = array_merge($parentValue, $formValue);
            }

            if ($formValue == $parentValue) {
                $overrides->{$key} = null;
            } else {
                $enum = ExerciseSetting::tryFrom($key);
                if ($enum && $settingClass = $enum->settingClass()) {
                    $overrides->{$key} = isset($formValue) ? $settingClass::from($formValue) : null;
                }
            }
        }

        if (isset($settingsConfig['overrides'])) {
            $overrides->gridOverrides = $settingsConfig['overrides'];
        }

        return $overrides;
    }

    public function openAddContent(): void
    {
        $this->addContentSearch = '';
        $this->addContentTab = 'program';
        Flux::modal('add-content')->show();
    }

    public function updatedAddContentTab(): void
    {
        $this->addContentSearch = '';
        unset($this->addContentOptions);
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
            'program' => ExerciseProgram::query()
                ->with('exerciseCategory:id,name,color')
                ->whereNull('parent_id')
                ->whereNull('parent_type')
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'exercise_category_id']),
            'exercise' => Exercise::query()
                ->with('category.rootAncestorOrSelf')
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'category_id']),
            default => collect(),
        };
    }

    public function addFromProgram(int $programId): void
    {
        $program = ExerciseProgram::findOrFail($programId);

        TrainingProgram::importProgram($program, (int) $this->group);

        unset($this->programs, $this->groupedPrograms);
        Flux::modal('add-content')->close();
    }

    public function addFromExercise(int $exerciseId): void
    {
        $exercise = Exercise::findOrFail($exerciseId);

        TrainingProgram::importExercise($exercise, (int) $this->group, categoryId: $exercise->category_id);

        unset($this->programs, $this->groupedPrograms);
        Flux::modal('add-content')->close();
    }

    public function removeTrainingProgram(int $trainingProgramId): void
    {
        TrainingProgram::findOrFail($trainingProgramId)->delete();
        unset($this->programs, $this->groupedPrograms);
    }

    public function openEditProgram(int $trainingProgramId): void
    {
        $trainingProgram = TrainingProgram::with('program.exerciseCategory', 'program.exercises')->findOrFail($trainingProgramId);

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
        unset($this->programs, $this->groupedPrograms);
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
        unset($this->programs, $this->groupedPrograms);
        Flux::modal('confirm-delete-program')->close();
        Flux::modal('edit-program')->close();
    }

    public function render()
    {
        return view('livewire.training.calendar-index');
    }
}
