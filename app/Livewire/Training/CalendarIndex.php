<?php

namespace App\Livewire\Training;

use App\Data\Training\Calendar\CalendarSettingsData;
use App\Data\Training\ExerciseProgramData;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExercisePlan;
use App\Models\Exercise\ExerciseProgram;
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

    public ?int $editingTrainingProgramId = null;

    public ?int $editingProgramId = null;

    public ?string $editingDate = null;

    public string $editingCellTime = '';

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

        unset($this->days, $this->weeks, $this->months, $this->title, $this->weekGridData, $this->overviewData, $this->athleteGridData);
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

            if ($this->viewMode === 'athlete') {
                $this->viewMode = 'program';
            }
        }

        unset($this->selectionName, $this->programs, $this->groupedPrograms, $this->slotMap, $this->cellSlots, $this->userOverrides, $this->overrideSlotMap, $this->slotState, $this->weekGridData, $this->athleteGridData);

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
            ->whereNull('training_program_slots.deleted_at')
            ->whereBetween('training_program_slots.datetime', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->selectRaw('training_programs.group_id, training_programs.user_id, training_programs.exercise_program_id, DATE(training_program_slots.datetime) as slot_date, TIME(training_program_slots.datetime) as slot_time, training_program_slots.active, exercise_programs.name as program_name, tags.color as category_color')
            ->get();

        $groupDates = [];
        $groupSlots = [];
        $userSlots = [];
        $programInfo = [];

        foreach ($slots as $slot) {
            $groupId = $slot->group_id;
            $date = $slot->slot_date;
            $epId = $slot->exercise_program_id;

            $programInfo[$epId] = [
                'name' => $slot->program_name,
                'color' => $slot->category_color,
            ];

            $groupDates[$groupId][$date] = true;

            if ($slot->user_id === null) {
                $groupSlots[$groupId][$epId][$date] = $slot->slot_time;
            } else {
                $userSlots[$groupId][$slot->user_id][$epId][$date] = [
                    'active' => (bool) $slot->active,
                    'time' => $slot->slot_time,
                ];
            }
        }

        $groups = UserGroup::with('members')->orderBy('name')->get();
        $result = [];

        foreach ($groups as $group) {
            $gid = $group->id;
            $members = [];

            foreach ($group->members as $member) {
                $uid = $member->id;
                $memberDates = [];

                foreach ($groupSlots[$gid] ?? [] as $epId => $dates) {
                    foreach ($dates as $date => $time) {
                        $overridden = isset($userSlots[$gid][$uid][$epId][$date]);

                        if ($overridden) {
                            if ($userSlots[$gid][$uid][$epId][$date]['active']) {
                                $memberDates[$date][] = [
                                    'name' => $programInfo[$epId]['name'],
                                    'color' => $programInfo[$epId]['color'],
                                    'time' => substr($userSlots[$gid][$uid][$epId][$date]['time'], 0, 5),
                                ];
                            }
                        } else {
                            $memberDates[$date][] = [
                                'name' => $programInfo[$epId]['name'],
                                'color' => $programInfo[$epId]['color'],
                                'time' => substr($time, 0, 5),
                            ];
                        }
                    }
                }

                foreach ($userSlots[$gid][$uid] ?? [] as $epId => $dates) {
                    foreach ($dates as $date => $info) {
                        if ($info['active'] && ! isset($groupSlots[$gid][$epId][$date])) {
                            $memberDates[$date][] = [
                                'name' => $programInfo[$epId]['name'],
                                'color' => $programInfo[$epId]['color'],
                                'time' => substr($info['time'], 0, 5),
                            ];
                        }
                    }
                }

                $members[] = [
                    'user' => $member,
                    'dates' => $memberDates,
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
    public function athleteGridData(): array
    {
        if ($this->group === '' || $this->user !== '') {
            return [];
        }

        [$start, $end] = $this->dateRange();
        $groupId = (int) $this->group;

        $slots = TrainingProgramSlot::query()
            ->join('training_programs', 'training_program_slots.training_program_id', '=', 'training_programs.id')
            ->join('exercise_programs', 'training_programs.exercise_program_id', '=', 'exercise_programs.id')
            ->leftJoin('tags', 'exercise_programs.exercise_category_id', '=', 'tags.id')
            ->whereNull('training_programs.deleted_at')
            ->whereNull('training_program_slots.deleted_at')
            ->where('training_programs.group_id', $groupId)
            ->whereBetween('training_program_slots.datetime', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->selectRaw('training_programs.user_id, training_programs.exercise_program_id, DATE(training_program_slots.datetime) as slot_date, TIME(training_program_slots.datetime) as slot_time, training_program_slots.active, exercise_programs.name as program_name, tags.color as category_color')
            ->get();

        $groupSlots = [];
        $userSlots = [];
        $programInfo = [];

        foreach ($slots as $slot) {
            $epId = $slot->exercise_program_id;

            $programInfo[$epId] = [
                'name' => $slot->program_name,
                'color' => $slot->category_color,
            ];

            if ($slot->user_id === null) {
                $groupSlots[$epId][$slot->slot_date] = $slot->slot_time;
            } else {
                $userSlots[$slot->user_id][$epId][$slot->slot_date] = [
                    'active' => (bool) $slot->active,
                    'time' => $slot->slot_time,
                ];
            }
        }

        $group = UserGroup::with('members')->findOrFail($groupId);
        $result = [];

        foreach ($group->members as $member) {
            $uid = $member->id;
            $memberDates = [];

            foreach ($groupSlots as $epId => $dates) {
                foreach ($dates as $date => $time) {
                    $overridden = isset($userSlots[$uid][$epId][$date]);

                    if ($overridden) {
                        if ($userSlots[$uid][$epId][$date]['active']) {
                            $memberDates[$date][] = [
                                'name' => $programInfo[$epId]['name'],
                                'color' => $programInfo[$epId]['color'],
                                'time' => substr($userSlots[$uid][$epId][$date]['time'], 0, 5),
                            ];
                        }
                    } else {
                        $memberDates[$date][] = [
                            'name' => $programInfo[$epId]['name'],
                            'color' => $programInfo[$epId]['color'],
                            'time' => substr($time, 0, 5),
                        ];
                    }
                }
            }

            foreach ($userSlots[$uid] ?? [] as $epId => $dates) {
                foreach ($dates as $date => $info) {
                    if ($info['active'] && ! isset($groupSlots[$epId][$date])) {
                        $memberDates[$date][] = [
                            'name' => $programInfo[$epId]['name'],
                            'color' => $programInfo[$epId]['color'],
                            'time' => substr($info['time'], 0, 5),
                        ];
                    }
                }
            }

            $result[] = [
                'user' => $member,
                'dates' => $memberDates,
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

        [$start, $end] = $this->dateRange();

        $eagerLoads = [
            'program.exerciseCategory',
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
    public function cellSlots(): array
    {
        $map = [];

        foreach ($this->slotMap as $key => $active) {
            if (! $active) {
                continue;
            }

            $datetime = substr($key, -19);
            $programId = substr($key, 0, strlen($key) - 20);
            $date = substr($datetime, 0, 10);
            $time = substr($datetime, 11, 5);

            $dateKey = $programId.'-'.$date;
            if (! isset($map[$dateKey])) {
                $map[$dateKey] = $time;
            }
        }

        return $map;
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
            if (! $active) {
                continue;
            }
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
            ->whereNull('training_programs.deleted_at')
            ->whereNull('training_program_slots.deleted_at')
            ->where('training_programs.group_id', $groupId)
            ->whereBetween('training_program_slots.datetime', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->selectRaw('training_programs.id as training_program_id, training_programs.user_id, training_programs.exercise_program_id, DATE(training_program_slots.datetime) as slot_date, TIME(training_program_slots.datetime) as slot_time, training_program_slots.active, exercise_programs.name as program_name, tags.color as category_color')
            ->get();

        $groupSlots = [];
        $userSlots = [];
        $programInfo = [];

        foreach ($slots as $slot) {
            $epId = $slot->exercise_program_id;

            $programInfo[$epId] = [
                'name' => $slot->program_name,
                'color' => $slot->category_color,
            ];

            if ($slot->user_id === null) {
                $time = substr($slot->slot_time, 0, 5);
                $groupSlots[$epId][$slot->slot_date][$time] = [
                    'tpId' => $slot->training_program_id,
                ];
            } else {
                $time = substr($slot->slot_time, 0, 5);
                $userSlots[$slot->user_id][$epId][$slot->slot_date][$time] = [
                    'active' => (bool) $slot->active,
                    'tpId' => $slot->training_program_id,
                ];
            }
        }

        $group = UserGroup::with('members')->findOrFail($groupId);

        $rawSlotsByDate = [];
        foreach ($group->members as $member) {
            $uid = $member->id;
            $memberOverrides = $userSlots[$uid] ?? [];

            foreach ($groupSlots as $epId => $dates) {
                foreach ($dates as $date => $times) {
                    foreach ($times as $groupTime => $info) {
                        $overrideAtGroupTime = $memberOverrides[$epId][$date][$groupTime] ?? null;

                        if ($overrideAtGroupTime !== null && ! $overrideAtGroupTime['active']) {
                            continue;
                        }

                        $key = $epId.'-'.$groupTime;
                        $rawSlotsByDate[$date][$key]['trainingProgramId'] = $info['tpId'];
                        $rawSlotsByDate[$date][$key]['name'] = $programInfo[$epId]['name'];
                        $rawSlotsByDate[$date][$key]['color'] = $programInfo[$epId]['color'];
                        $rawSlotsByDate[$date][$key]['time'] = $groupTime;
                        $rawSlotsByDate[$date][$key]['userNames'][] = $member->name;
                    }
                }
            }

            foreach ($memberOverrides as $epId => $dates) {
                foreach ($dates as $date => $times) {
                    $groupTimes = $groupSlots[$epId][$date] ?? [];

                    foreach ($times as $time => $override) {
                        if (! $override['active']) {
                            continue;
                        }

                        if (isset($groupTimes[$time])) {
                            continue;
                        }

                        $firstGroupTpId = ! empty($groupTimes) ? reset($groupTimes)['tpId'] : null;
                        $key = $epId.'-'.$time;
                        $rawSlotsByDate[$date][$key]['trainingProgramId'] = $firstGroupTpId ?? $override['tpId'];
                        $rawSlotsByDate[$date][$key]['name'] = $programInfo[$epId]['name'];
                        $rawSlotsByDate[$date][$key]['color'] = $programInfo[$epId]['color'];
                        $rawSlotsByDate[$date][$key]['time'] = $time;
                        $rawSlotsByDate[$date][$key]['userNames'][] = $member->name;
                    }
                }
            }
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

    public function cycleSlot(int $trainingProgramId, string $date): void
    {
        $this->cancelEditing();

        $dateKey = $trainingProgramId.'-'.$date;
        $currentTime = $this->cellSlots[$dateKey] ?? null;

        $program = TrainingProgram::findOrFail($trainingProgramId);
        $isOverride = $this->user !== '' && $program->isGroupLevel();

        $toggle = function (string $datetime) use ($program, $isOverride): void {
            if ($isOverride) {
                $this->toggleOverrideSlot($program, $datetime);
            } else {
                $this->toggleDirectSlot($program->id, $datetime);
            }
        };

        if ($currentTime !== null) {
            $toggle($date.' '.$currentTime.':00');
        }

        $nextTime = match ($currentTime) {
            null => '09:00',
            '09:00' => '14:00',
            default => null,
        };

        if ($nextTime !== null) {
            $toggle($date.' '.$nextTime.':00');
        }

        unset($this->programs, $this->groupedPrograms, $this->slotMap, $this->cellSlots, $this->userOverrides, $this->overrideSlotMap, $this->slotState, $this->weekGridData, $this->athleteGridData);

    }

    public function startEditingCell(int $trainingProgramId, string $date): void
    {
        $dateKey = $trainingProgramId.'-'.$date;
        $currentTime = $this->cellSlots[$dateKey] ?? '09:00';

        $this->editingProgramId = $trainingProgramId;
        $this->editingDate = $date;
        $this->editingCellTime = $currentTime;
    }

    public function updatedEditingCellTime(): void
    {
        if ($this->editingProgramId === null || $this->editingDate === null || $this->editingCellTime === '') {
            return;
        }

        $this->setSlotTime($this->editingProgramId, $this->editingDate, $this->editingCellTime);

        $this->editingProgramId = null;
        $this->editingDate = null;
        $this->editingCellTime = '';
    }

    public function cancelEditing(): void
    {
        $this->editingProgramId = null;
        $this->editingDate = null;
        $this->editingCellTime = '';
    }

    public function setSlotTime(int $trainingProgramId, string $date, string $time): void
    {
        $dateKey = $trainingProgramId.'-'.$date;
        $currentTime = $this->cellSlots[$dateKey] ?? null;

        if ($currentTime === $time) {
            return;
        }

        $program = TrainingProgram::findOrFail($trainingProgramId);
        $isOverride = $this->user !== '' && $program->isGroupLevel();

        $toggle = function (string $datetime) use ($program, $isOverride): void {
            if ($isOverride) {
                $this->toggleOverrideSlot($program, $datetime);
            } else {
                $this->toggleDirectSlot($program->id, $datetime);
            }
        };

        if ($currentTime !== null) {
            $toggle($date.' '.$currentTime.':00');
        }

        $toggle($date.' '.$time.':00');

        unset($this->programs, $this->groupedPrograms, $this->slotMap, $this->cellSlots, $this->userOverrides, $this->overrideSlotMap, $this->slotState, $this->weekGridData, $this->athleteGridData);

    }

    public function toggleSlot(int $trainingProgramId, string $datetime): void
    {
        $program = TrainingProgram::findOrFail($trainingProgramId);

        if ($this->user !== '' && $program->isGroupLevel()) {
            $this->toggleOverrideSlot($program, $datetime);
        } else {
            $this->toggleDirectSlot($trainingProgramId, $datetime);
        }

        unset($this->programs, $this->groupedPrograms, $this->slotMap, $this->cellSlots, $this->userOverrides, $this->overrideSlotMap, $this->slotState, $this->weekGridData, $this->athleteGridData);

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
            $this->cellSlots,
            $this->userOverrides,
            $this->overrideSlotMap,
            $this->slotState,
            $this->weekGridData,
            $this->overviewData,
            $this->athleteGridData
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
        $userId = $this->user !== '' ? (int) $this->user : null;

        if ($groupId === null) {
            return [];
        }

        $query = TrainingProgram::query()
            ->with('program')
            ->where('group_id', $groupId);

        if ($userId !== null) {
            $query->where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            });
        } else {
            $query->whereNull('user_id');
        }

        return $query
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
            $this->addError('quickProgramId', 'Select a program.');
            $hasErrors = true;
        }

        if ($this->quickAthleteOptions->isNotEmpty() && empty($this->quickSelectedAthletes)) {
            $this->addError('quickSelectedAthletes', 'Select athletes.');
            $hasErrors = true;
        }

        if ($hasErrors) {
            return;
        }

        $startTime = $period === 'pm' ? '14:00' : '09:00';
        $datetime = $date.' '.$startTime.':00';
        $trainingProgramId = $this->quickProgramId;

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

        $program = TrainingProgram::find($trainingProgramId);

        if ($program !== null && $program->isGroupLevel()) {
            $allMemberIds = $this->quickAthleteOptions->pluck('id')->all();
            $selectedMembers = array_map('intval', $this->quickSelectedAthletes);
            $deselectedMembers = array_values(array_diff($allMemberIds, $selectedMembers));

            $otherGroupSlotTimes = TrainingProgramSlot::query()
                ->where('training_program_id', $trainingProgramId)
                ->where('datetime', 'like', $date.'%')
                ->where('datetime', '!=', $datetime)
                ->pluck('datetime')
                ->all();

            foreach ($selectedMembers as $userId) {
                foreach ($otherGroupSlotTimes as $otherDatetime) {
                    $overrideProgram = TrainingProgram::findOrCreateOverride($program, $userId);

                    $existingOverride = TrainingProgramSlot::query()
                        ->where('training_program_id', $overrideProgram->id)
                        ->where('datetime', $otherDatetime)
                        ->first();

                    if ($existingOverride === null) {
                        TrainingProgramSlot::create([
                            'training_program_id' => $overrideProgram->id,
                            'datetime' => $otherDatetime,
                            'active' => false,
                        ]);
                    } elseif ($existingOverride->active) {
                        $existingOverride->update(['active' => false]);
                    }
                }

                $overrideProgram = TrainingProgram::query()
                    ->where('group_id', $program->group_id)
                    ->where('user_id', $userId)
                    ->where('exercise_program_id', $program->exercise_program_id)
                    ->first();

                if ($overrideProgram === null) {
                    continue;
                }

                $existingOverride = TrainingProgramSlot::query()
                    ->where('training_program_id', $overrideProgram->id)
                    ->where('datetime', $datetime)
                    ->where('active', false)
                    ->first();

                if ($existingOverride !== null) {
                    $existingOverride->forceDelete();

                    if ($overrideProgram->slots()->count() === 0) {
                        $overrideProgram->forceDelete();
                    }
                }
            }

            foreach ($deselectedMembers as $userId) {
                $overrideProgram = TrainingProgram::findOrCreateOverride($program, $userId);

                $existingOverride = TrainingProgramSlot::query()
                    ->where('training_program_id', $overrideProgram->id)
                    ->where('datetime', $datetime)
                    ->first();

                if ($existingOverride === null) {
                    TrainingProgramSlot::create([
                        'training_program_id' => $overrideProgram->id,
                        'datetime' => $datetime,
                        'active' => false,
                    ]);
                } elseif ($existingOverride->active) {
                    $existingOverride->update(['active' => false]);
                }
            }
        }

        unset($this->programs, $this->groupedPrograms, $this->slotMap, $this->cellSlots, $this->userOverrides, $this->overrideSlotMap, $this->slotState, $this->weekGridData, $this->athleteGridData);
    }

    public function quickRemoveWeekSlot(int $trainingProgramId, string $date, string $startTime): void
    {
        $datetime = $date.' '.$startTime.':00';
        $program = TrainingProgram::find($trainingProgramId);

        if ($program === null) {
            return;
        }

        if ($this->user !== '' && $program->isGroupLevel()) {
            $this->quickRemoveAthleteSlot($program, $datetime);
        } else {
            $this->quickRemoveGroupSlot($program, $trainingProgramId, $datetime);
        }

        unset($this->programs, $this->groupedPrograms, $this->slotMap, $this->cellSlots, $this->userOverrides, $this->overrideSlotMap, $this->slotState, $this->weekGridData, $this->athleteGridData);
    }

    protected function quickRemoveAthleteSlot(TrainingProgram $program, string $datetime): void
    {
        $userId = (int) $this->user;
        $group = UserGroup::with('members')->find($program->group_id);

        if ($group === null) {
            return;
        }

        $otherMemberIds = $group->members->pluck('id')->filter(fn ($id) => $id !== $userId)->all();

        $optedOutUserIds = TrainingProgramSlot::query()
            ->join('training_programs', 'training_program_slots.training_program_id', '=', 'training_programs.id')
            ->where('training_programs.group_id', $program->group_id)
            ->where('training_programs.exercise_program_id', $program->exercise_program_id)
            ->whereIn('training_programs.user_id', $otherMemberIds)
            ->where('training_program_slots.datetime', $datetime)
            ->where('training_program_slots.active', false)
            ->pluck('training_programs.user_id')
            ->all();

        $activeOtherMembers = array_diff($otherMemberIds, $optedOutUserIds);

        if (count($activeOtherMembers) > 0) {
            $overrideProgram = TrainingProgram::findOrCreateOverride($program, $userId);

            $existingOverride = TrainingProgramSlot::query()
                ->where('training_program_id', $overrideProgram->id)
                ->where('datetime', $datetime)
                ->first();

            if ($existingOverride === null) {
                TrainingProgramSlot::create([
                    'training_program_id' => $overrideProgram->id,
                    'datetime' => $datetime,
                    'active' => false,
                ]);
            } elseif ($existingOverride->active) {
                $existingOverride->update(['active' => false]);
            }

            return;
        }

        $this->quickRemoveGroupSlot($program, $program->id, $datetime);
    }

    protected function quickRemoveGroupSlot(TrainingProgram $program, int $trainingProgramId, string $datetime): void
    {
        if ($program->isGroupLevel()) {
            $overrideSlots = TrainingProgramSlot::query()
                ->join('training_programs', 'training_program_slots.training_program_id', '=', 'training_programs.id')
                ->where('training_programs.group_id', $program->group_id)
                ->where('training_programs.exercise_program_id', $program->exercise_program_id)
                ->whereNotNull('training_programs.user_id')
                ->where('training_program_slots.datetime', $datetime)
                ->select('training_program_slots.*', 'training_programs.id as tp_id')
                ->get();

            foreach ($overrideSlots as $overrideSlot) {
                $overrideSlot->forceDelete();

                $overrideProgram = TrainingProgram::find($overrideSlot->tp_id);
                if ($overrideProgram !== null && $overrideProgram->slots()->count() === 0) {
                    $overrideProgram->forceDelete();
                }
            }
        }

        $slot = TrainingProgramSlot::withTrashed()
            ->where('training_program_id', $trainingProgramId)
            ->where('datetime', $datetime)
            ->first();

        if ($slot !== null) {
            $slot->forceDelete();
        }
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

        if ($programChanged || $timeChanged) {
            $oldSlot = TrainingProgramSlot::withTrashed()
                ->where('training_program_id', (int) $originalProgramId)
                ->where('datetime', $originalDatetime)
                ->first();

            if ($oldSlot !== null) {
                $oldSlot->forceDelete();
            }

            if ($programChanged) {
                $oldProgram = TrainingProgram::find((int) $originalProgramId);
                if ($oldProgram !== null && $oldProgram->isGroupLevel()) {
                    TrainingProgramSlot::query()
                        ->join('training_programs', 'training_program_slots.training_program_id', '=', 'training_programs.id')
                        ->where('training_programs.group_id', $oldProgram->group_id)
                        ->where('training_programs.exercise_program_id', $oldProgram->exercise_program_id)
                        ->whereNotNull('training_programs.user_id')
                        ->where('training_program_slots.datetime', $originalDatetime)
                        ->select('training_program_slots.*', 'training_programs.id as tp_id')
                        ->get()
                        ->each(function ($overrideSlot) {
                            $overrideSlot->forceDelete();

                            $overrideProgram = TrainingProgram::find($overrideSlot->tp_id);
                            if ($overrideProgram !== null && $overrideProgram->slots()->count() === 0) {
                                $overrideProgram->forceDelete();
                            }
                        });
                }
            }
        }

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

        $deselectedMembers = $data['deselected_members'] ?? [];
        $selectedMembers = $data['selected_members'] ?? [];

        $program = TrainingProgram::find($trainingProgramId);

        if ($program !== null && $program->isGroupLevel()) {
            $date = $data['date'];

            $otherGroupSlotTimes = TrainingProgramSlot::query()
                ->where('training_program_id', $trainingProgramId)
                ->where('datetime', 'like', $date.'%')
                ->where('datetime', '!=', $datetime)
                ->pluck('datetime')
                ->all();

            foreach ($selectedMembers as $userId) {
                foreach ($otherGroupSlotTimes as $otherDatetime) {
                    $overrideProgram = TrainingProgram::findOrCreateOverride($program, $userId);

                    $existingOverride = TrainingProgramSlot::query()
                        ->where('training_program_id', $overrideProgram->id)
                        ->where('datetime', $otherDatetime)
                        ->first();

                    if ($existingOverride === null) {
                        TrainingProgramSlot::create([
                            'training_program_id' => $overrideProgram->id,
                            'datetime' => $otherDatetime,
                            'active' => false,
                        ]);
                    } elseif ($existingOverride->active) {
                        $existingOverride->update(['active' => false]);
                    }
                }

                $overrideProgram = TrainingProgram::query()
                    ->where('group_id', $program->group_id)
                    ->where('user_id', $userId)
                    ->where('exercise_program_id', $program->exercise_program_id)
                    ->first();

                if ($overrideProgram === null) {
                    continue;
                }

                $existingOverride = TrainingProgramSlot::query()
                    ->where('training_program_id', $overrideProgram->id)
                    ->where('datetime', $datetime)
                    ->where('active', false)
                    ->first();

                if ($existingOverride !== null) {
                    $existingOverride->forceDelete();

                    if ($overrideProgram->slots()->count() === 0) {
                        $overrideProgram->forceDelete();
                    }
                }
            }

            foreach ($deselectedMembers as $userId) {
                $overrideProgram = TrainingProgram::findOrCreateOverride($program, $userId);

                $existingOverride = TrainingProgramSlot::query()
                    ->where('training_program_id', $overrideProgram->id)
                    ->where('datetime', $datetime)
                    ->first();

                if ($existingOverride === null) {
                    TrainingProgramSlot::create([
                        'training_program_id' => $overrideProgram->id,
                        'datetime' => $datetime,
                        'active' => false,
                    ]);
                } elseif ($existingOverride->active) {
                    $existingOverride->update(['active' => false]);
                }
            }
        }

        unset($this->programs, $this->groupedPrograms, $this->slotMap, $this->cellSlots, $this->userOverrides, $this->overrideSlotMap, $this->slotState, $this->weekGridData, $this->athleteGridData);

    }

    #[On('week-slot.deleted')]
    public function onWeekSlotDeleted(array $data): void
    {
        $trainingProgramId = (int) $data['training_program_id'];
        $datetime = $data['date'].' '.$data['start_time'].':00';

        $program = TrainingProgram::find($trainingProgramId);

        if ($program !== null && $program->isGroupLevel()) {
            $overrideSlots = TrainingProgramSlot::query()
                ->join('training_programs', 'training_program_slots.training_program_id', '=', 'training_programs.id')
                ->where('training_programs.group_id', $program->group_id)
                ->where('training_programs.exercise_program_id', $program->exercise_program_id)
                ->whereNotNull('training_programs.user_id')
                ->where('training_program_slots.datetime', $datetime)
                ->select('training_program_slots.*', 'training_programs.id as tp_id')
                ->get();

            foreach ($overrideSlots as $overrideSlot) {
                $overrideSlot->forceDelete();

                $overrideProgram = TrainingProgram::find($overrideSlot->tp_id);
                if ($overrideProgram !== null && $overrideProgram->slots()->count() === 0) {
                    $overrideProgram->forceDelete();
                }
            }
        }

        $slot = TrainingProgramSlot::withTrashed()
            ->where('training_program_id', $trainingProgramId)
            ->where('datetime', $datetime)
            ->first();

        if ($slot !== null) {
            $slot->forceDelete();
        }

        unset($this->programs, $this->groupedPrograms, $this->slotMap, $this->cellSlots, $this->userOverrides, $this->overrideSlotMap, $this->slotState, $this->weekGridData, $this->athleteGridData);

    }

    public function openAddContent(): void
    {
        $this->addContentSearch = '';
        $this->addContentTab = 'plan';
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
            'plan' => ExercisePlan::query()
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name']),
            'program' => ExerciseProgram::query()
                ->with('exerciseCategory:id,name,color')
                ->whereNull('owner_id')
                ->whereNull('owner_type')
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'exercise_category_id']),
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
        $userId = $this->viewMode === 'programs' ? null : ($this->user !== '' ? (int) $this->user : null);

        TrainingProgram::importFromPlan($plan, $groupId, $userId);

        unset($this->programs, $this->groupedPrograms);
        Flux::modal('add-content')->close();
    }

    public function addFromProgram(int $programId): void
    {
        $program = ExerciseProgram::findOrFail($programId);
        $groupId = (int) $this->group;
        $userId = $this->viewMode === 'programs' ? null : ($this->user !== '' ? (int) $this->user : null);

        TrainingProgram::importProgram($program, $groupId, $userId);

        unset($this->programs, $this->groupedPrograms);
        Flux::modal('add-content')->close();
    }

    public function addFromExercise(int $exerciseId): void
    {
        $exercise = Exercise::findOrFail($exerciseId);
        $groupId = (int) $this->group;
        $userId = $this->viewMode === 'programs' ? null : ($this->user !== '' ? (int) $this->user : null);

        TrainingProgram::importExercise($exercise, $groupId, $userId, categoryId: $exercise->category_id);

        unset($this->programs, $this->groupedPrograms);
        Flux::modal('add-content')->close();
    }

    public function removeTrainingProgram(int $trainingProgramId): void
    {
        $program = TrainingProgram::findOrFail($trainingProgramId);

        if ($this->user !== '' && $program->isGroupLevel()) {
            return;
        }

        $program->delete();
        unset($this->programs, $this->groupedPrograms);
    }

    public function openEditProgram(int $trainingProgramId): void
    {
        $trainingProgram = TrainingProgram::with('program.exerciseCategory', 'program.exercises')->findOrFail($trainingProgramId);

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
        unset($this->programs, $this->groupedPrograms);
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
        unset($this->programs, $this->groupedPrograms);
        Flux::modal('confirm-delete-program')->close();
        Flux::modal('edit-program')->close();
    }

    public function render()
    {
        return view('livewire.training.calendar-index');
    }
}
