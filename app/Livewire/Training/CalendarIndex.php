<?php

namespace App\Livewire\Training;

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\Metrics\HeartRateMetric;
use App\Data\Athlete\Metric\Metrics\OneRepMaxMetric;
use App\Data\Athlete\Metric\MetricSubmissionData;
use App\Data\Training\Calendar\CalendarSettingsData;
use App\Data\Training\Config\EffectiveExerciseConfig;
use App\Data\Training\ExerciseProgramData;
use App\Models\Athlete\MetricSubmission;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramBlockTypeEnum;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Support\WeekOptions;
use App\Training\ProjectedOneRepMaxService;
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

    #[Url(except: 'overview', history: true)]
    public string $view = 'overview';

    #[Url(except: '')]
    public string $group = '';

    #[Url(except: '')]
    public string $user = '';

    #[Url(except: '')]
    public string $planCategory = '';

    #[Url(except: 'ungrouped')]
    public string $planBlock = 'ungrouped';

    #[Url(except: '')]
    public string $planProgram = '';

    #[Url(except: 'mine')]
    public string $groupFilter = 'mine';

    public string $planProgramName = '';

    public CalendarSettingsData $calendarSettings;

    public int $weekStartsOn;

    public int $weekEndsOn;

    public string $addContentSearch = '';

    public string $addContentTab = 'program';

    public ?int $editingTrainingProgramId = null;

    public ?int $pendingMetricDeleteId = null;

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

    public function updatedView(): void
    {
        if ($this->view === 'plan' && $this->planBlock === 'ungrouped' && $this->planCategory !== '') {
            $this->selectOverlappingBlock();
        }

        if ($this->view === 'plan') {
            $this->syncPlanProgramName();
        }

        unset($this->weekGridData);
    }

    public function updatedPlanCategory(): void
    {
        $this->planBlock = 'ungrouped';
        $this->selectOverlappingBlock();
        $this->planProgram = '';
        $options = $this->planProgramOptions;
        if ($options->isNotEmpty()) {
            $this->planProgram = (string) $options->keys()->first();
        }
        $this->syncPlanProgramName();
    }

    public function updatedPlanBlock(): void
    {
        $this->planProgram = '';
        unset($this->planBlockGoal, $this->planHasBlock, $this->planMeasuredData);
        $options = $this->planProgramOptions;
        if ($options->isNotEmpty()) {
            $this->planProgram = (string) $options->keys()->first();
        }
        $this->syncPlanProgramName();
    }

    public function updatedPlanProgram(): void
    {
        $this->syncPlanProgramName();
    }

    public function savePlanProgramName(): void
    {
        $program = $this->planSelectedProgram;
        if (! $program) {
            return;
        }

        $program->program->update(['name' => $this->planProgramName]);
        unset($this->planProgramOptions);
    }

    protected function syncPlanProgramName(): void
    {
        $program = $this->planSelectedProgram;
        $this->planProgramName = $program?->program->name ?? '';
    }

    public function navigateToPlan(int $trainingProgramId): void
    {
        $trainingProgram = TrainingProgram::with('program.exerciseCategory')->find($trainingProgramId);
        if (! $trainingProgram) {
            return;
        }

        $this->view = 'plan';
        $this->planCategory = (string) ($trainingProgram->program->exercise_category_id ?? 0);
        $this->planBlock = 'ungrouped';
        $this->planProgram = (string) $trainingProgramId;
        $this->planProgramName = $trainingProgram->program->name;

        $this->selectOverlappingBlock();

        unset($this->weekGridData);
    }

    protected function selectOverlappingBlock(): void
    {
        $categoryId = (int) $this->planCategory;
        if ($categoryId === 0 || $this->group === '') {
            return;
        }

        $today = Carbon::today();

        $blocks = TrainingProgramBlock::query()
            ->where('group_id', (int) $this->group)
            ->where('category_id', $categoryId)
            ->where('type', TrainingProgramBlockTypeEnum::Category)
            ->whereNull('parent_id')
            ->orderBy('start')
            ->get();

        if ($blocks->isEmpty()) {
            return;
        }

        $overridesByParent = collect();
        if ($this->user !== '') {
            $overridesByParent = TrainingProgramBlock::query()
                ->whereNotNull('parent_id')
                ->where('user_id', (int) $this->user)
                ->whereIn('parent_id', $blocks->pluck('id'))
                ->get()
                ->keyBy('parent_id');
        }

        $effectiveBlocks = $blocks->map(function ($block) use ($overridesByParent) {
            $override = $overridesByParent->get($block->id);
            if ($override && ! $override->active) {
                return null;
            }

            return $override ?? $block;
        })->filter();

        if ($effectiveBlocks->isEmpty()) {
            return;
        }

        $overlapping = $effectiveBlocks->first(function ($block) use ($today) {
            $end = $block->end ?? $block->start;

            return $block->start->lte($today) && $end->gte($today);
        });

        $this->planBlock = (string) ($overlapping?->id ?? $effectiveBlocks->first()->id);
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

        unset($this->days, $this->weeks, $this->months, $this->title, $this->weekGridData, $this->overviewData, $this->allBlocks, $this->categoryBlocks, $this->metricCellData);
    }

    #[On('sidebar-selection-changed')]
    public function onSidebarSelectionChanged(array $selected): void
    {
        $previousGroup = $this->group;
        $previousView = $this->view;

        $this->group = '';
        $this->user = '';

        if (count($selected) === 0) {
            $this->view = 'overview';
            $this->planCategory = '';
            $this->planBlock = 'ungrouped';
            $this->planProgram = '';
            unset($this->selectionName, $this->programs, $this->groupedPrograms, $this->slotMap, $this->programCellSlots, $this->weekGridData, $this->allBlocks, $this->categoryBlocks, $this->metricCellData, $this->planMeasuredData);

            return;
        }

        $first = $selected[0];
        $this->group = (string) $first['group'];

        if (isset($first['user'])) {
            $this->user = (string) $first['user'];
        }

        $sameGroup = $this->group === $previousGroup;

        if (! $sameGroup) {
            $this->view = 'overview';
            $this->planCategory = '';
            $this->planBlock = 'ungrouped';
            $this->planProgram = '';
            $this->planProgramName = '';
        } elseif ($this->view === 'plan' && $this->planCategory !== '') {
            $this->selectOverlappingBlock();
        }

        unset($this->selectionName, $this->programs, $this->groupedPrograms, $this->slotMap, $this->programCellSlots, $this->weekGridData, $this->allBlocks, $this->categoryBlocks, $this->metricCellData, $this->planMeasuredData);
    }

    #[On('group-filter-changed')]
    public function onGroupFilterChanged(string $filter): void
    {
        $this->groupFilter = $filter;
        unset($this->overviewData);
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

        $groupsQuery = UserGroup::with('members')->orderBy('name');

        if ($this->groupFilter === 'mine') {
            $groupsQuery->where('owner_id', auth()->id());
        }

        $groups = $groupsQuery->get();
        $groupIds = $groups->pluck('id');

        $slots = TrainingProgramSlot::query()
            ->join('training_programs', 'training_program_slots.training_program_id', '=', 'training_programs.id')
            ->join('exercise_programs', 'training_programs.exercise_program_id', '=', 'exercise_programs.id')
            ->leftJoin('tags', 'exercise_programs.exercise_category_id', '=', 'tags.id')
            ->whereNull('training_programs.deleted_at')
            ->whereIn('training_programs.group_id', $groupIds)
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
    public function planCategoryOptions(): Collection
    {
        $grouped = $this->groupedPrograms;

        $options = $grouped->mapWithKeys(function (array $group, int $categoryId) {
            $name = $group['category']?->name ?? __('Uncategorized');

            return [$categoryId => $name];
        });

        if ($options->isNotEmpty() && $this->planCategory === '') {
            $this->planCategory = (string) $options->keys()->first();
        }

        return $options;
    }

    #[Computed]
    public function planBlockOptions(): Collection
    {
        $options = collect(['ungrouped' => __('Ungrouped')]);

        if ($this->planCategory === '' || $this->group === '') {
            return $options;
        }

        $categoryId = (int) $this->planCategory;
        if ($categoryId === 0) {
            return $options;
        }

        $blocks = TrainingProgramBlock::query()
            ->where('group_id', (int) $this->group)
            ->where('category_id', $categoryId)
            ->where('type', TrainingProgramBlockTypeEnum::Category)
            ->whereNull('parent_id')
            ->orderBy('start')
            ->get();

        $overridesByParent = collect();
        if ($this->user !== '') {
            $overridesByParent = TrainingProgramBlock::query()
                ->whereNotNull('parent_id')
                ->where('user_id', (int) $this->user)
                ->whereIn('parent_id', $blocks->pluck('id'))
                ->get()
                ->keyBy('parent_id');
        }

        foreach ($blocks as $block) {
            $override = $overridesByParent->get($block->id);

            if ($override && ! $override->active) {
                continue;
            }

            $effective = $override ?? $block;
            $label = $effective->note ?: $effective->start->format('M d').($effective->end ? ' - '.$effective->end->format('M d') : '');
            $options[$effective->id] = $label;
        }

        return $options;
    }

    #[Computed]
    public function planProgramOptions(): Collection
    {
        if ($this->planCategory === '') {
            return collect();
        }

        $categoryId = (int) $this->planCategory;
        $entries = $this->programs->filter(function (TrainingProgram $entry) use ($categoryId) {
            return ($entry->program->exercise_category_id ?? 0) === $categoryId;
        });

        $programIds = $entries->pluck('id');

        if ($this->planBlock === 'ungrouped') {
            $slotQuery = TrainingProgramSlot::query()
                ->whereIn('training_program_id', $programIds);

            $this->applyUngroupedFilter($slotQuery);

            $scheduledIds = $slotQuery->distinct()->pluck('training_program_id');
            $entries = $entries->whereIn('id', $scheduledIds);
        } else {
            $block = TrainingProgramBlock::find((int) $this->planBlock);
            if ($block) {
                $blockStart = $block->start->startOfDay();
                $blockEnd = ($block->end ?? $block->start)->endOfDay();

                $scheduledIds = TrainingProgramSlot::query()
                    ->whereIn('training_program_id', $programIds)
                    ->whereBetween('datetime', [$blockStart, $blockEnd])
                    ->distinct()
                    ->pluck('training_program_id');

                $entries = $entries->whereIn('id', $scheduledIds);
            }
        }

        $options = $entries->mapWithKeys(fn (TrainingProgram $entry) => [
            $entry->id => $entry->program->name,
        ]);

        if ($options->isNotEmpty() && ($this->planProgram === '' || ! $options->has((int) $this->planProgram))) {
            $this->planProgram = (string) $options->keys()->first();
        }

        return $options;
    }

    #[Computed]
    public function planBlockGoal(): ?int
    {
        if ($this->planBlock === 'ungrouped') {
            return null;
        }

        $block = TrainingProgramBlock::find((int) $this->planBlock);

        return $block?->config?->goal;
    }

    #[Computed]
    public function planHasBlock(): bool
    {
        return $this->planBlock !== 'ungrouped';
    }

    /** @return array{measuredReps: ?int, measuredWeight: ?float} */
    #[Computed]
    public function planMeasuredData(): array
    {
        if ($this->user === '') {
            return ['measuredReps' => 1, 'measuredWeight' => 50];
        }

        if ($this->planBlock === 'ungrouped') {
            return ['measuredReps' => null, 'measuredWeight' => null];
        }

        $block = TrainingProgramBlock::find((int) $this->planBlock);
        if (! $block) {
            return ['measuredReps' => null, 'measuredWeight' => null];
        }

        $submission = MetricSubmission::query()
            ->forAthlete((int) $this->user)
            ->forMetric(MetricEnum::OneRepMax)
            ->where('recorded_at', '<=', $block->start->format('Y-m-d'))
            ->orderByDesc('recorded_at')
            ->with('values')
            ->first();

        if (! $submission) {
            return ['measuredReps' => null, 'measuredWeight' => null];
        }

        $fieldValues = $submission->values->pluck('value', 'field')->all();
        $metric = OneRepMaxMetric::from($fieldValues);

        return [
            'measuredReps' => $metric->measuredReps,
            'measuredWeight' => $metric->measuredWeight,
        ];
    }

    /** @return array{maxHR: ?int, iatPercent: ?int} */
    #[Computed]
    public function planHeartRateData(): array
    {
        if ($this->user === '') {
            return ['maxHR' => null, 'iatPercent' => null];
        }

        $submission = MetricSubmission::query()
            ->forAthlete((int) $this->user)
            ->forMetric(MetricEnum::HeartRate)
            ->manual()
            ->where('recorded_at', '<=', now()->format('Y-m-d'))
            ->orderByDesc('recorded_at')
            ->with('values')
            ->first();

        if (! $submission) {
            return ['maxHR' => null, 'iatPercent' => null];
        }

        $fieldValues = $submission->values->pluck('value', 'field')->all();
        $metric = HeartRateMetric::from($fieldValues);

        return [
            'maxHR' => $metric->heartRate,
            'iatPercent' => $metric->anaerobicThreshold,
        ];
    }

    #[Computed]
    public function planHasAutoWeightExercises(): bool
    {
        $program = $this->planSelectedProgram;
        if (! $program) {
            return false;
        }

        foreach ($program->program->exercises as $exercise) {
            $config = $exercise->config;
            if (in_array('weight', $config->settings ?? [])
                && ($config->weight?->mode ?? 'manual') === 'automatic') {
                return true;
            }
        }

        return false;
    }

    #[Computed]
    public function planSelectedProgram(): ?TrainingProgram
    {
        if ($this->planProgram === '') {
            return null;
        }

        return TrainingProgram::with('program.exerciseCategory', 'program.exercises')
            ->find((int) $this->planProgram);
    }

    #[Computed]
    public function planScheduleInfo(): array
    {
        if ($this->planProgram === '') {
            return ['weeks' => 0, 'sessionsPerWeek' => 1, 'scheduled' => false, 'weekLabels' => [], 'weekSessions' => []];
        }

        $slotQuery = TrainingProgramSlot::query()
            ->where('training_program_id', (int) $this->planProgram);

        if ($this->user !== '') {
            $slotQuery->where('user_id', (int) $this->user);
        }

        if ($this->planBlock === 'ungrouped') {
            $this->applyUngroupedFilter($slotQuery);
        } else {
            $block = TrainingProgramBlock::find((int) $this->planBlock);
            if ($block) {
                $slotQuery->whereBetween('datetime', [
                    $block->start->startOfDay(),
                    ($block->end ?? $block->start)->endOfDay(),
                ]);
            }
        }

        $slotDates = $slotQuery->pluck('datetime')
            ->map(fn ($dt) => Carbon::parse($dt)->format('Y-m-d'))
            ->unique()
            ->sort()
            ->values();

        $scheduledWeeks = [];
        foreach ($slotDates as $date) {
            $d = Carbon::parse($date);
            $key = $d->isoWeekYear().'-'.$d->isoWeek();
            if (! isset($scheduledWeeks[$key])) {
                $scheduledWeeks[$key] = ['week' => $d->isoWeek(), 'year' => $d->isoWeekYear()];
            }
        }
        $scheduledWeeks = array_values($scheduledWeeks);

        $weeks = count($scheduledWeeks);
        if ($weeks === 0) {
            return ['weeks' => 0, 'sessionsPerWeek' => 1, 'scheduled' => false, 'weekLabels' => [], 'weekSessions' => []];
        }

        $weekSlotCounts = $slotDates->groupBy(fn ($date) => Carbon::parse($date)->isoWeekYear().'-'.Carbon::parse($date)->isoWeek())
            ->map->count();
        $sessionsPerWeek = max(1, (int) $weekSlotCounts->max());

        $weekLabels = [];
        $weekSessions = [];
        foreach ($scheduledWeeks as $i => $weekInfo) {
            $monday = Carbon::now()->setISODate($weekInfo['year'], $weekInfo['week'], 1);
            $sunday = $monday->copy()->addDays(6);
            $dateRange = $monday->format('d.m').' - '.$sunday->format('d.m');
            $weekLabels[$i] = 'W'.$weekInfo['week'].', '.$weekInfo['year']
                .'<br><span class="text-[10px] font-normal text-zinc-400 dark:text-zinc-500">'.$dateRange.'</span>';
            $key = $weekInfo['year'].'-'.$weekInfo['week'];
            $weekSessions[$i] = (int) ($weekSlotCounts[$key] ?? 1);
        }

        return ['weeks' => $weeks, 'sessionsPerWeek' => $sessionsPerWeek, 'scheduled' => true, 'weekLabels' => $weekLabels, 'weekSessions' => $weekSessions];
    }

    protected function getActiveBlockDateRanges(): array
    {
        if ($this->planCategory === '' || $this->group === '') {
            return [];
        }

        $blocks = TrainingProgramBlock::query()
            ->where('group_id', (int) $this->group)
            ->where('category_id', (int) $this->planCategory)
            ->where('type', TrainingProgramBlockTypeEnum::Category)
            ->whereNull('parent_id')
            ->get();

        $overridesByParent = collect();
        if ($this->user !== '') {
            $overridesByParent = TrainingProgramBlock::query()
                ->whereNotNull('parent_id')
                ->where('user_id', (int) $this->user)
                ->whereIn('parent_id', $blocks->pluck('id'))
                ->get()
                ->keyBy('parent_id');
        }

        $ranges = [];
        foreach ($blocks as $block) {
            $override = $overridesByParent->get($block->id);
            if ($override && ! $override->active) {
                continue;
            }
            $effective = $override ?? $block;
            $ranges[] = [
                $effective->start->startOfDay(),
                ($effective->end ?? $effective->start)->endOfDay(),
            ];
        }

        return $ranges;
    }

    protected function applyUngroupedFilter($query): void
    {
        $ranges = $this->getActiveBlockDateRanges();
        foreach ($ranges as [$start, $end]) {
            $query->whereNotBetween('datetime', [$start, $end]);
        }
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
    public function allBlocks(): array
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

        $query = TrainingProgramBlock::query()
            ->where('group_id', $groupId)
            ->whereNull('category_id')
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

        $typeOrder = array_flip(array_map(fn ($case) => $case->value, TrainingProgramBlockTypeEnum::cases()));
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
    public function categoryBlocks(): array
    {
        $days = $this->days;
        $totalDays = count($days);

        if (! $this->hasSelection()) {
            return [];
        }

        [$start, $end] = $this->dateRange();
        $groupId = (int) $this->group;

        $dayIndex = [];
        foreach ($days as $i => $day) {
            $dayIndex[$day['date']] = $i;
        }

        $query = TrainingProgramBlock::query()
            ->where('group_id', $groupId)
            ->whereNotNull('category_id')
            ->whereNull('user_id')
            ->where('type', TrainingProgramBlockTypeEnum::Category)
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($q2) use ($start, $end) {
                    $q2->where('start', '<=', $end->format('Y-m-d'))
                        ->where(function ($q3) use ($start) {
                            $q3->where('end', '>=', $start->format('Y-m-d'))
                                ->orWhereNull('end');
                        });
                })->orWhereBetween('start', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
            });

        $blocks = $query->get();

        $overridesByParent = collect();
        if ($this->user !== '') {
            $overridesByParent = TrainingProgramBlock::query()
                ->where('group_id', $groupId)
                ->whereNotNull('parent_id')
                ->where('user_id', (int) $this->user)
                ->whereIn('parent_id', $blocks->pluck('id'))
                ->get()
                ->keyBy('parent_id');
        }

        $byCat = $blocks->groupBy('category_id');
        $result = [];

        foreach ($byCat as $catId => $catBlocks) {
            $grouped = $catBlocks->groupBy(fn ($n) => $n->start->format('Y-m-d').'|'.($n->end?->format('Y-m-d') ?? '').'|'.$n->note);

            $ranges = [];
            foreach ($grouped as $group) {
                $rep = $group->first();
                $override = $overridesByParent->get($rep->id);

                if ($override && ! $override->active) {
                    continue;
                }

                $effective = $override ?? $rep;
                $noteStart = $effective->start->format('Y-m-d');
                $noteEnd = ($effective->end ?? $effective->start)->format('Y-m-d');
                $clampedStart = max($noteStart, $start->format('Y-m-d'));
                $clampedEnd = min($noteEnd, $end->format('Y-m-d'));

                if (! isset($dayIndex[$clampedStart])) {
                    continue;
                }

                $startIdx = $dayIndex[$clampedStart];
                $endIdx = $dayIndex[$clampedEnd] ?? ($totalDays - 1);

                $ranges[] = [
                    'id' => $effective->id,
                    'note' => $effective->note,
                    'startIdx' => $startIdx,
                    'endIdx' => $endIdx,
                    'colspan' => $endIdx - $startIdx + 1,
                    'overridden' => $override !== null,
                ];
            }

            usort($ranges, fn ($a, $b) => $a['startIdx'] <=> $b['startIdx']);

            $laneEnds = [];
            foreach ($ranges as &$range) {
                $placed = false;
                foreach ($laneEnds as $lane => $laneEnd) {
                    if ($laneEnd < $range['startIdx']) {
                        $range['lane'] = $lane;
                        $laneEnds[$lane] = $range['endIdx'];
                        $placed = true;
                        break;
                    }
                }
                if (! $placed) {
                    $range['lane'] = count($laneEnds);
                    $laneEnds[] = $range['endIdx'];
                }
            }
            unset($range);

            $result[$catId] = [
                'notes' => $ranges,
                'laneCount' => count($laneEnds),
                'totalDays' => $totalDays,
            ];
        }

        return $result;
    }

    #[Computed]
    public function metricCellData(): array
    {
        if ($this->user === '') {
            return [];
        }

        [$start, $end] = $this->dateRange();

        $submissions = MetricSubmission::query()
            ->forAthlete((int) $this->user)
            ->with('values')
            ->whereBetween('recorded_at', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get();

        $map = [];

        foreach ($submissions as $submission) {
            $dateKey = $submission->recorded_at->format('Y-m-d');
            $metricKey = $submission->metric->value;
            $cellKey = "{$metricKey}-{$dateKey}";
            $isProjected = $submission->owner_type !== null;

            if ($isProjected && isset($map[$cellKey]) && ! ($map[$cellKey]['isProjected'] ?? false)) {
                continue;
            }

            $metricClass = $submission->metric->metricClass();
            $fieldValues = $submission->values->pluck('value', 'field')->all();
            $metricInstance = $metricClass::from($fieldValues);

            $label = match ($submission->metric) {
                MetricEnum::OneRepMax => isset($fieldValues['estimated1RM']) ? (int) round((float) $fieldValues['estimated1RM']) : null,
                MetricEnum::HeartRate => $fieldValues['heartRate'] ?? null,
            };

            $map[$cellKey] = [
                'id' => $submission->id,
                'label' => $label,
                'summary' => $metricInstance->summary(),
                'isProjected' => $isProjected,
                'data' => MetricSubmissionData::fromModel($submission)->toArray(),
            ];
        }

        return $map;
    }

    #[Computed]
    public function groupMetricCellData(): array
    {
        if ($this->group === '' || $this->user !== '') {
            return [];
        }

        [$start, $end] = $this->dateRange();

        $group = UserGroup::with('members')->find((int) $this->group);
        if ($group === null) {
            return [];
        }

        $memberIds = $group->members->pluck('id');

        $submissions = MetricSubmission::query()
            ->whereIn('user_id', $memberIds)
            ->with(['values', 'user'])
            ->whereBetween('recorded_at', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get();

        $memberCount = $memberIds->count();
        $map = [];

        foreach ($submissions as $submission) {
            $dateKey = $submission->recorded_at->format('Y-m-d');
            $metricKey = $submission->metric->value;
            $cellKey = "{$metricKey}-{$dateKey}";
            $metricClass = $submission->metric->metricClass();
            $fieldValues = $submission->values->pluck('value', 'field')->all();
            $metricInstance = $metricClass::from($fieldValues);

            if (! isset($map[$cellKey])) {
                $map[$cellKey] = [
                    'count' => 0,
                    'entries' => [],
                    'memberCount' => $memberCount,
                ];
            }

            $isProjected = $submission->owner_type !== null;

            $map[$cellKey]['count']++;
            $map[$cellKey]['entries'][] = [
                'summary' => $metricInstance->summary(),
                'athlete' => $submission->user->name,
                'user_id' => $submission->user_id,
                'submission_id' => $submission->id,
                'isProjected' => $isProjected,
                'data' => MetricSubmissionData::fromModel($submission)->toArray(),
            ];
        }

        return $map;
    }

    #[Computed]
    public function currentMetricValues(): array
    {
        if ($this->user === '') {
            return [];
        }

        $result = [];

        foreach (MetricEnum::cases() as $metricCase) {
            $submission = MetricSubmission::query()
                ->forAthlete((int) $this->user)
                ->forMetric($metricCase)
                ->manual()
                ->where('recorded_at', '<=', now()->format('Y-m-d'))
                ->orderByDesc('recorded_at')
                ->with('values')
                ->first();

            if ($submission) {
                $fieldValues = $submission->values->pluck('value', 'field')->all();
                $metricInstance = $metricCase->metricClass()::from($fieldValues);
                $result[$metricCase->value] = [
                    'summary' => $metricInstance->summary(),
                    'recorded_at' => $submission->recorded_at->format('d.m.Y'),
                    'data' => MetricSubmissionData::from($submission)->toArray(),
                ];
            }
        }

        return $result;
    }

    public function openCurrentMetric(string $metricValue): void
    {
        $current = $this->currentMetricValues[$metricValue] ?? null;

        if (! $current) {
            return;
        }

        $eventData = array_merge($current['data'], ['metric' => $metricValue]);
        $metricLabel = MetricEnum::from($metricValue)->label();

        $this->dispatch('open-calendar-metric-form', data: $eventData, title: __('Edit Metric')." ({$metricLabel})");
    }

    public function openMetricCell(string $metricValue, string $date): void
    {
        if ($this->user === '') {
            return;
        }

        $key = "{$metricValue}-{$date}";
        $existingData = $this->metricCellData[$key] ?? null;

        if ($existingData) {
            $eventData = array_merge($existingData['data'], ['metric' => $metricValue]);
        } else {
            $eventData = [
                'metric' => $metricValue,
                'recorded_at' => $date,
                'user_id' => (int) $this->user,
            ];
        }

        $metricLabel = MetricEnum::from($metricValue)->label();
        $title = $existingData ? __('Edit Metric') : __('Add Metric');

        $this->dispatch('open-calendar-metric-form', data: $eventData, title: "{$title} ({$metricLabel})");
    }

    public function openGroupMetricCell(string $metricValue, string $date, ?int $userId = null, ?int $submissionId = null): void
    {
        if ($this->group === '') {
            return;
        }

        $cellKey = "{$metricValue}-{$date}";

        if ($userId !== null && $submissionId !== null) {
            $groupCell = $this->groupMetricCellData[$cellKey] ?? null;
            $entry = null;

            if ($groupCell) {
                foreach ($groupCell['entries'] as $e) {
                    if ($e['submission_id'] === $submissionId) {
                        $entry = $e;
                        break;
                    }
                }
            }

            if ($entry) {
                $eventData = array_merge($entry['data'], ['metric' => $metricValue]);
                $metricLabel = MetricEnum::from($metricValue)->label();
                $this->dispatch('open-calendar-metric-form', data: $eventData, title: __('Edit Metric')." ({$metricLabel})");
            }

            return;
        }

        $group = UserGroup::with('members')->find((int) $this->group);
        if ($group === null) {
            return;
        }

        $existingUserIds = [];
        if (isset($this->groupMetricCellData[$cellKey])) {
            $existingUserIds = array_column($this->groupMetricCellData[$cellKey]['entries'], 'user_id');
        }

        $availableAthletes = $group->members
            ->reject(fn ($member) => in_array($member->id, $existingUserIds, true))
            ->map(fn ($member) => ['id' => $member->id, 'name' => $member->name])
            ->values()
            ->all();

        $eventData = [
            'metric' => $metricValue,
            'recorded_at' => $date,
            'user_id' => null,
            '_group_mode' => true,
            '_available_athletes' => $availableAthletes,
        ];

        $metricLabel = MetricEnum::from($metricValue)->label();
        $this->dispatch('open-calendar-metric-form', data: $eventData, title: __('Add Metric')." ({$metricLabel})");
    }

    #[On('calendar-metric-form.delete-requested')]
    public function onMetricDeleteRequested(array $data): void
    {
        $id = $data['id'] ?? null;
        if ($id === null) {
            return;
        }

        $this->pendingMetricDeleteId = (int) $id;

        Flux::modal('calendar-metric-form')->close();
        Flux::modal('confirm-delete-metric')->show();
    }

    public function deleteMetricSubmission(): void
    {
        if (empty($this->pendingMetricDeleteId)) {
            return;
        }

        $submission = MetricSubmission::find($this->pendingMetricDeleteId);
        $this->pendingMetricDeleteId = null;

        Flux::modal('confirm-delete-metric')->close();

        if ($submission) {
            $submission->delete();
        }

        unset($this->metricCellData, $this->currentMetricValues, $this->groupMetricCellData, $this->planMeasuredData);
    }

    #[On('calendar-metric-form.submitted')]
    public function onMetricFormSubmitted(array $data): void
    {
        if (empty($data['_persisted'])) {
            $metric = MetricEnum::from($data['metric']);
            $metricClass = $metric->metricClass();

            $submission = new MetricSubmissionData(
                id: $data['id'] ?? null,
                user_id: (int) ($data['user_id'] ?? $this->user),
                metric: $metric,
                recorded_by: auth()->id(),
                recorded_at: $data['recorded_at'] ?? null,
                data: $metricClass::from($data['data'] ?? []),
            );

            $submission->persist();

            if ($metric === MetricEnum::OneRepMax) {
                $projectedService = app(ProjectedOneRepMaxService::class);
                $projectedService->syncForAthleteBlocks((int) ($data['user_id'] ?? $this->user));
            }
        }

        unset($this->metricCellData);
        unset($this->currentMetricValues);
        unset($this->groupMetricCellData);
        unset($this->planMeasuredData);
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

    public function openBlock(string $date): void
    {
        $this->dispatch('open-block', data: [
            'date' => $date,
            'groupId' => $this->group !== '' ? (int) $this->group : null,
            'userId' => $this->user !== '' ? (int) $this->user : null,
        ]);
    }

    public function editBlock(int $blockId): void
    {
        $block = TrainingProgramBlock::find($blockId);
        if (! $block) {
            return;
        }

        $isCategoryBlock = $block->category_id !== null;

        if ($isCategoryBlock && $this->user !== '') {
            $groupBlock = $block->parent_id ? $block->parent : $block;
            $override = $block->parent_id ? $block : $groupBlock->athleteOverride((int) $this->user);

            $this->dispatch('open-block', data: [
                'blockId' => $override?->id,
                'parentId' => $groupBlock->id,
                'groupId' => $this->group !== '' ? (int) $this->group : null,
                'userId' => (int) $this->user,
            ]);

            return;
        }

        $this->dispatch('open-block', data: [
            'blockId' => $blockId,
            'groupId' => $this->group !== '' ? (int) $this->group : null,
            'userId' => $isCategoryBlock ? null : ($this->user !== '' ? (int) $this->user : null),
        ]);
    }

    public function editBlockForProjectedMetric(int $submissionId): void
    {
        $submission = MetricSubmission::find($submissionId);
        if (! $submission || $submission->owner_type !== TrainingProgramBlock::class) {
            return;
        }

        $this->editBlock($submission->owner_id);
    }

    public function openCategoryBlock(string $date, int $categoryId): void
    {
        $tag = Tag::find($categoryId);

        $this->dispatch('open-block', data: [
            'date' => $date,
            'groupId' => $this->group !== '' ? (int) $this->group : null,
            'categoryId' => $categoryId,
            'categorySlug' => $tag?->slug,
            'categoryName' => $tag?->name,
        ]);
    }

    public function openBlockRange(string $startDate, string $endDate): void
    {
        $this->dispatch('open-block', data: [
            'date' => $startDate,
            'endDate' => $endDate,
            'groupId' => $this->group !== '' ? (int) $this->group : null,
            'userId' => $this->user !== '' ? (int) $this->user : null,
        ]);
    }

    public function openCategoryBlockRange(string $startDate, string $endDate, int $categoryId): void
    {
        $tag = Tag::find($categoryId);

        $this->dispatch('open-block', data: [
            'date' => $startDate,
            'endDate' => $endDate,
            'groupId' => $this->group !== '' ? (int) $this->group : null,
            'categoryId' => $categoryId,
            'categorySlug' => $tag?->slug,
            'categoryName' => $tag?->name,
        ]);
    }

    #[On('block.submitted')]
    public function onBlockSubmitted(array $data): void
    {
        $groupId = $data['groupId'];
        $editingBlockId = $data['editing_block_id'] ?? null;
        $selectedMembers = $data['selected_members'] ?? [];
        $userId = $data['userId'] ?? null;
        $parentId = $data['parentId'] ?? null;
        $type = TrainingProgramBlockTypeEnum::from($data['type'] ?? 'focus');
        $color = $data['color'] ?? null;
        $categoryId = $data['categoryId'] ?? null;
        $config = $data['config'] ?? null;
        $isCategoryBlock = $categoryId !== null;
        $projectedService = app(ProjectedOneRepMaxService::class);

        if ($parentId !== null) {
            if ($editingBlockId !== null) {
                $oldBlock = TrainingProgramBlock::find($editingBlockId);
                if ($oldBlock) {
                    $projectedService->removeForBlock($oldBlock);
                }
                TrainingProgramBlock::destroy($editingBlockId);
            }

            $parentBlock = TrainingProgramBlock::find($parentId);
            if ($parentBlock) {
                $childBlock = TrainingProgramBlock::create([
                    'group_id' => $groupId,
                    'user_id' => $userId,
                    'parent_id' => $parentId,
                    'category_id' => $parentBlock->category_id,
                    'type' => $parentBlock->type,
                    'start' => $data['start'],
                    'end' => $data['end'] ?: null,
                    'note' => $data['note'],
                    'color' => $color ?: null,
                    'config' => $config,
                    'active' => true,
                ]);

                $projectedService->syncForBlock($childBlock);
                $projectedService->syncForBlock($parentBlock->fresh());
            }

            unset($this->allBlocks, $this->categoryBlocks, $this->planBlockGoal, $this->planMeasuredData, $this->metricCellData, $this->currentMetricValues, $this->groupMetricCellData);

            return;
        }

        if ($editingBlockId !== null) {
            $oldBlock = TrainingProgramBlock::find($editingBlockId);
            if ($oldBlock) {
                $projectedService->removeForBlock($oldBlock);
            }

            if ($isCategoryBlock) {
                TrainingProgramBlock::destroy($editingBlockId);
            } elseif ($userId !== null && empty($selectedMembers)) {
                TrainingProgramBlock::destroy($editingBlockId);
            } else {
                if ($oldBlock) {
                    TrainingProgramBlock::query()
                        ->where('group_id', $groupId)
                        ->where('type', $oldBlock->type)
                        ->where('start', $oldBlock->start)
                        ->where('note', $oldBlock->note)
                        ->delete();
                }
            }
        }

        $blockData = [
            'group_id' => $groupId,
            'type' => $type,
            'start' => $data['start'],
            'end' => $data['end'] ?: null,
            'note' => $data['note'],
            'color' => $color ?: null,
            'category_id' => $categoryId,
            'config' => $config,
        ];

        if ($isCategoryBlock) {
            $newBlock = TrainingProgramBlock::create([...$blockData, 'user_id' => null]);
            $projectedService->syncForBlock($newBlock);
        } elseif ($userId !== null && empty($selectedMembers)) {
            $newBlock = TrainingProgramBlock::create([...$blockData, 'user_id' => $userId]);
            $projectedService->syncForBlock($newBlock);
        } else {
            foreach ($selectedMembers as $memberId) {
                $newBlock = TrainingProgramBlock::create([...$blockData, 'user_id' => $memberId]);
                $projectedService->syncForBlock($newBlock);
            }
        }

        unset($this->allBlocks, $this->categoryBlocks, $this->planBlockGoal, $this->planMeasuredData, $this->metricCellData, $this->currentMetricValues, $this->groupMetricCellData);
    }

    #[On('block.deleted')]
    public function onBlockDeleted(array $data): void
    {
        $editingBlockId = $data['editing_block_id'] ?? null;
        $groupId = $data['groupId'];
        $userId = $data['userId'] ?? null;

        if ($editingBlockId === null) {
            return;
        }

        $existingBlock = TrainingProgramBlock::find($editingBlockId);
        if (! $existingBlock) {
            return;
        }

        $projectedService = app(ProjectedOneRepMaxService::class);

        if ($existingBlock->parent_id !== null) {
            $projectedService->removeForBlock($existingBlock);
            $existingBlock->update(['active' => false]);

            $parentBlock = TrainingProgramBlock::find($existingBlock->parent_id);
            if ($parentBlock) {
                $projectedService->syncForBlock($parentBlock);
            }

            unset($this->allBlocks, $this->categoryBlocks, $this->planBlockGoal, $this->planMeasuredData, $this->metricCellData, $this->currentMetricValues, $this->groupMetricCellData);

            return;
        }

        $projectedService->removeForBlock($existingBlock);

        if ($existingBlock->category_id !== null) {
            $children = TrainingProgramBlock::where('parent_id', $editingBlockId)->get();
            foreach ($children as $child) {
                $projectedService->removeForBlock($child);
            }
            TrainingProgramBlock::where('parent_id', $editingBlockId)->delete();
            TrainingProgramBlock::destroy($editingBlockId);
        } elseif ($userId !== null) {
            TrainingProgramBlock::destroy($editingBlockId);
        } else {
            TrainingProgramBlock::query()
                ->where('group_id', $groupId)
                ->where('type', $existingBlock->type)
                ->where('start', $existingBlock->start)
                ->where('note', $existingBlock->note)
                ->delete();
        }

        unset($this->allBlocks, $this->categoryBlocks, $this->planBlockGoal, $this->planMeasuredData, $this->metricCellData, $this->currentMetricValues, $this->groupMetricCellData);
    }

    public function toggleExerciseDisabled(int $exerciseId, int $exerciseProgramId): void
    {
        $exerciseProgram = ExerciseProgram::findOrFail($exerciseProgramId);
        $config = $exerciseProgram->config;
        $userId = $this->user !== '' ? (int) $this->user : null;

        if ($userId !== null) {
            $planOverrides = $config->defaultExerciseOverrides($exerciseId);
            $userOverrides = $config->userExerciseOverrides($userId, $exerciseId);
            $currentlyDisabled = EffectiveExerciseConfig::resolveDisabled($planOverrides, $userOverrides);
            $userOverrides->disabled = $currentlyDisabled ? false : true;

            $defaultDisabled = $planOverrides->disabled ?? false;
            if ($userOverrides->disabled === $defaultDisabled) {
                $userOverrides->disabled = null;
            }

            $config->setUserExerciseOverrides($userId, $exerciseId, $userOverrides);
        } else {
            $overrides = $config->defaultExerciseOverrides($exerciseId);
            $overrides->disabled = ! ($overrides->disabled ?? false) ?: null;
            $config->setDefaultExerciseOverrides($exerciseId, $overrides);
        }

        $exerciseProgram->config = $config;
        $exerciseProgram->save();

        unset($this->programs, $this->groupedPrograms);
    }

    public function isExerciseDisabled(int $exerciseId, ExerciseProgram $program): bool
    {
        $config = $program->config;
        $planOverrides = $config->defaultExerciseOverrides($exerciseId);

        if ($this->user !== '') {
            $userOverrides = $config->userExerciseOverrides((int) $this->user, $exerciseId);

            return EffectiveExerciseConfig::resolveDisabled($planOverrides, $userOverrides);
        }

        return $planOverrides->disabled ?? false;
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
        if ($this->view === 'plan' && $this->planProgramName === '' && $this->planProgram !== '') {
            $this->syncPlanProgramName();
        }

        return view('livewire.training.calendar-index');
    }
}
