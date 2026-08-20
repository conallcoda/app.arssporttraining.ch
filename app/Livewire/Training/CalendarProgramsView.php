<?php

namespace App\Livewire\Training;

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\Metrics\HeartRateMetric;
use App\Data\Athlete\Metric\Metrics\OneRepMaxMetric;
use App\Data\Athlete\Metric\Metrics\ReadinessMetric;
use App\Data\Athlete\Metric\MetricSubmissionData;
use App\Data\Athlete\Metric\ReadinessMetricData;
use App\Data\Training\Calendar\CalendarSettingsData;
use App\Data\Training\Config\EffectiveExerciseConfig;
use App\Data\Training\Config\ExerciseOverrides;
use App\Data\Training\ExerciseProgramData;
use App\Exceptions\DuplicateManualMetricSubmission;
use App\Models\Athlete\MetricSubmission;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramBlockTypeEnum;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Support\Training\BlockModalPayloadBuilder;
use App\Support\Training\MetricModalPayloadBuilder;
use App\Support\Training\WeekSlotModalPayloadBuilder;
use App\Training\CalendarBlockService;
use App\Training\CalendarDateService;
use App\Training\ProjectedOneRepMaxService;
use App\Training\TrainingPlanRevisionService;
use App\Training\TrainingSessionEditGuard;
use App\Training\TrainingSessionRebuildDispatcher;
use App\Training\TrainingStateRevisionService;
use Carbon\Carbon;
use Coda\Cms\Support\ColorPalette;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class CalendarProgramsView extends Component
{
    public int $groupId;

    public ?int $userId = null;

    public CalendarSettingsData $calendarSettings;

    public int $weekStartsOn;

    public int $weekEndsOn;

    public ?int $editingTrainingProgramId = null;

    public ?int $duplicatingTrainingProgramId = null;

    public ?int $pendingMetricDeleteId = null;

    public bool $metricsLoaded = false;

    public int $metricsRenderKey = 0;

    public function loadMetrics(): void
    {
        $this->metricsLoaded = true;
    }

    #[On('calendar-selection-changed')]
    public function onSelectionChanged(int $groupId, ?int $userId = null): void
    {
        $groupChanged = $this->groupId !== $groupId;
        $this->groupId = $groupId;
        $this->userId = $userId;

        if ($groupChanged) {
            unset(
                $this->programs,
                $this->groupedPrograms,
                $this->programCellSlots,
                $this->athleteSlotOrder,
                $this->allBlocks,
                $this->categoryBlocks,
                $this->visibleMetrics,
                $this->metricSummaryDates,
                $this->metricCellData,
                $this->groupMetricCellData,
                $this->currentMetricValues,
                $this->groupCurrentMetricValues,
            );
        } else {
            unset(
                $this->programCellSlots,
                $this->athleteSlotOrder,
                $this->metricCellData,
                $this->groupMetricCellData,
                $this->currentMetricValues,
                $this->groupCurrentMetricValues,
                $this->metricSummaryDates,
            );
        }

    }

    #[On('calendar-range-changed')]
    public function onCalendarRangeChanged(array $settings, int $weekStartsOn, int $weekEndsOn): void
    {
        $this->calendarSettings = CalendarSettingsData::from($settings);
        $this->weekStartsOn = $weekStartsOn;
        $this->weekEndsOn = $weekEndsOn;

        unset(
            $this->days,
            $this->weeks,
            $this->months,
            $this->programCellSlots,
            $this->athleteSlotOrder,
            $this->allBlocks,
            $this->categoryBlocks,
            $this->visibleMetrics,
            $this->metricSummaryDates,
            $this->metricCellData,
            $this->groupMetricCellData,
            $this->currentMetricValues,
            $this->groupCurrentMetricValues,
        );
    }

    /** @return array{Carbon, Carbon} */
    protected function dateRange(): array
    {
        return app(CalendarDateService::class)->dateRange($this->calendarSettings, $this->weekStartsOn, $this->weekEndsOn);
    }

    #[Computed]
    public function programs(): Collection
    {
        [$start, $end] = $this->dateRange();

        return TrainingProgram::with([
            'program.exerciseCategory',
            'program.exercises',
        ])
            ->visibleInDateRange($this->groupId, $start, $end, $this->userId)
            ->orderBy('sort')
            ->get();
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
    public function days(): array
    {
        [$start, $end] = $this->dateRange();

        return app(CalendarDateService::class)->buildDays($start, $end);
    }

    #[Computed]
    public function weeks(): array
    {
        [$start, $end] = $this->dateRange();

        return app(CalendarDateService::class)->buildWeeks($start, $end);
    }

    #[Computed]
    public function months(): array
    {
        [$start, $end] = $this->dateRange();

        return app(CalendarDateService::class)->buildMonths($start, $end);
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
            ->selectRaw('training_program_id, DATE(datetime) as slot_date, MIN(TIME(datetime)) as first_time, COUNT(DISTINCT user_id) as user_count')
            ->groupByRaw('training_program_id, DATE(datetime)');

        if ($this->userId !== null) {
            $query->where('user_id', $this->userId);
        }

        $map = [];

        foreach ($query->get() as $row) {
            $dateKey = $row->training_program_id.'-'.$row->slot_date;
            $time = substr($row->first_time, 0, 5);

            $map[$dateKey] = [
                '_userCount' => $row->user_count,
                $time => [
                    ['name' => '', 'userId' => 0],
                ],
            ];
        }

        return $map;
    }

    #[Computed]
    public function athleteSlotOrder(): array
    {
        if ($this->userId === null) {
            return [];
        }

        $visibleKeys = array_fill_keys(array_keys($this->programCellSlots), true);

        if ($visibleKeys === []) {
            return [];
        }

        $programCategoryMap = [];
        foreach ($this->groupedPrograms as $categoryId => $group) {
            foreach ($group['entries'] as $entry) {
                $programCategoryMap[$entry->id] = $categoryId;
            }
        }

        if ($programCategoryMap === []) {
            return [];
        }

        [$start, $end] = $this->dateRange();
        $blockRangesByCategory = $this->effectiveCategoryBlockRanges($start, $end);

        if ($blockRangesByCategory === []) {
            return [];
        }

        $datesByProgram = $this->loadProgramDatesForVisibleBlocks(array_keys($programCategoryMap), $blockRangesByCategory);
        $blockSlots = [];

        foreach ($datesByProgram as $programId => $dates) {
            $categoryId = $programCategoryMap[(int) $programId] ?? null;

            if ($categoryId === null) {
                continue;
            }

            foreach ($dates as $date) {
                foreach ($blockRangesByCategory[$categoryId] ?? [] as $block) {
                    if ($date < $block['start'] || $date > $block['end']) {
                        continue;
                    }

                    $key = $programId.'-'.$date;
                    $blockSlots[$block['id'].'-'.$programId][] = ['date' => $date, 'key' => $key];

                    break;
                }
            }
        }

        $order = [];

        foreach ($blockSlots as $entries) {
            usort($entries, fn ($a, $b) => strcmp($a['date'], $b['date']));

            foreach ($entries as $i => $entry) {
                if (isset($visibleKeys[$entry['key']])) {
                    $order[$entry['key']] = $i + 1;
                }
            }
        }

        return $order;
    }

    /**
     * @return array<int, array<int, array{id: int, start: string, end: string}>>
     */
    protected function effectiveCategoryBlockRanges(Carbon $start, Carbon $end): array
    {
        $service = app(CalendarBlockService::class);
        $data = $service->getCategoryBlocksForDateRange(
            $this->groupId,
            $this->userId,
            $start,
            $end,
        );

        $blocks = $data['blocks'];
        $overridesByParent = $data['overridesByParent'];
        $ranges = [];

        foreach ($blocks as $block) {
            if ($block->user_id !== null) {
                $effective = $block;
            } else {
                $override = $overridesByParent->get($block->id);
                if ($override && ! $override->active) {
                    continue;
                }

                $effective = $override ?? $block;
            }

            $ranges[(int) $effective->category_id][] = [
                'id' => (int) $effective->id,
                'start' => $effective->start->format('Y-m-d'),
                'end' => ($effective->end ?? $effective->start)->format('Y-m-d'),
            ];
        }

        return $ranges;
    }

    /**
     * @param  int[]  $programIds
     * @param  array<int, array<int, array{id: int, start: string, end: string}>>  $blockRangesByCategory
     * @return array<int, array<int, string>>
     */
    protected function loadProgramDatesForVisibleBlocks(array $programIds, array $blockRangesByCategory): array
    {
        if ($programIds === [] || $blockRangesByCategory === []) {
            return [];
        }

        $allRanges = [];
        foreach ($blockRangesByCategory as $ranges) {
            foreach ($ranges as $range) {
                $allRanges[] = $range;
            }
        }

        if ($allRanges === []) {
            return [];
        }

        $rangeStart = collect($allRanges)->min('start');
        $rangeEnd = collect($allRanges)->max('end');

        if (! is_string($rangeStart) || ! is_string($rangeEnd)) {
            return [];
        }

        $query = TrainingProgramSlot::query()
            ->selectRaw('training_program_id, DATE(datetime) as slot_date')
            ->whereIn('training_program_id', $programIds)
            ->whereBetween('datetime', [
                Carbon::parse($rangeStart)->startOfDay(),
                Carbon::parse($rangeEnd)->endOfDay(),
            ])
            ->distinct()
            ->orderBy('slot_date');

        if ($this->userId !== null) {
            $query->where('user_id', $this->userId);
        }

        $datesByProgram = [];

        foreach ($query->get() as $row) {
            $datesByProgram[(int) $row->training_program_id][] = (string) $row->slot_date;
        }

        return $datesByProgram;
    }

    #[Computed]
    public function allBlocks(): array
    {
        $days = $this->days;
        $totalDays = count($days);
        $empty = ['notes' => [], 'laneCount' => 0, 'totalDays' => $totalDays];

        [$start, $end] = $this->dateRange();

        $dayIndex = [];
        foreach ($days as $i => $day) {
            $dayIndex[$day['date']] = $i;
        }

        $query = TrainingProgramBlock::query()
            ->where('group_id', $this->groupId)
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

        if ($this->userId !== null) {
            $query->where('user_id', $this->userId);
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

        [$start, $end] = $this->dateRange();

        $dayIndex = [];
        foreach ($days as $i => $day) {
            $dayIndex[$day['date']] = $i;
        }

        $service = app(CalendarBlockService::class);
        $data = $service->getCategoryBlocksForDateRange(
            $this->groupId,
            $this->userId,
            $start,
            $end,
        );

        $blocks = $data['blocks'];
        $overridesByParent = $data['overridesByParent'];

        $byCat = $blocks->groupBy('category_id');
        $result = [];

        foreach ($byCat as $catId => $catBlocks) {
            $grouped = $catBlocks->groupBy(fn ($n) => $n->start->format('Y-m-d').'|'.($n->end?->format('Y-m-d') ?? '').'|'.$n->note);

            $ranges = [];
            foreach ($grouped as $group) {
                $rep = $group->first();

                if ($rep->user_id !== null) {
                    $effective = $rep;
                } else {
                    $override = $overridesByParent->get($rep->id);

                    if ($override && ! $override->active) {
                        continue;
                    }

                    $effective = $override ?? $rep;
                }
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
                    'overridden' => isset($override),
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
    public function visibleMetrics(): array
    {
        return MetricEnum::cases();
    }

    #[Computed]
    public function metricSummaryDates(): array
    {
        [$start, $end] = $this->dateRange();

        if ($this->userId !== null) {
            $dates = MetricSubmission::query()
                ->forAthlete($this->userId)
                ->whereBetween('recorded_at', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->distinct()
                ->orderBy('recorded_at')
                ->pluck('recorded_at')
                ->map(fn ($date) => $date->format('Y-m-d'))
                ->all();
        } else {
            $memberIds = UserGroup::find($this->groupId)?->members->pluck('id')->all() ?? [];

            if (empty($memberIds)) {
                return [];
            }

            $dates = MetricSubmission::query()
                ->whereIn('user_id', $memberIds)
                ->whereBetween('recorded_at', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->distinct()
                ->orderBy('recorded_at')
                ->pluck('recorded_at')
                ->map(fn ($date) => $date->format('Y-m-d'))
                ->all();
        }

        return array_flip($dates);
    }

    #[Computed]
    public function metricCellData(): array
    {
        if ($this->userId === null) {
            return [];
        }

        [$start, $end] = $this->dateRange();

        $submissions = MetricSubmission::query()
            ->forAthlete($this->userId)
            ->with('values')
            ->whereBetween('recorded_at', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->orderBy('id')
            ->get();

        $map = [];

        foreach ($submissions as $submission) {
            $dateKey = $submission->recorded_at->format('Y-m-d');
            $metricKey = $submission->metric->value;
            $cellKey = "{$metricKey}-{$dateKey}";
            $isProjected = $submission->owner_type === TrainingProgramBlock::class;

            if ($isProjected && isset($map[$cellKey]) && ! ($map[$cellKey]['isProjected'] ?? false)) {
                continue;
            }

            $metricClass = $submission->metric->metricClass();
            $fieldValues = $submission->values->pluck('value', 'field')->all();
            $metricInstance = $metricClass::from($fieldValues);

            $label = match ($submission->metric) {
                MetricEnum::OneRepMax => isset($fieldValues['estimated1RM']) ? (int) round((float) $fieldValues['estimated1RM']) : null,
                MetricEnum::HeartRate => $fieldValues['heartRate'] ?? null,
                MetricEnum::Readiness => isset($fieldValues['readinessScore'])
                    ? number_format((float) $fieldValues['readinessScore'], 1)
                    : (($score = $metricInstance->data()->readinessScore()) !== null ? number_format($score, 1) : null),
            };

            $map[$cellKey] = [
                'id' => $submission->id,
                'label' => $label,
                'summary' => $metricInstance->summary(),
                'colorClass' => $this->metricColorClass($submission->metric, $fieldValues, $metricInstance),
                'isProjected' => $isProjected,
                'data' => MetricSubmissionData::fromModel($submission)->toArray(),
            ];
        }

        return $map;
    }

    #[Computed]
    public function groupMetricCellData(): array
    {
        if ($this->userId !== null) {
            return [];
        }

        [$start, $end] = $this->dateRange();

        $group = UserGroup::with('members')->find($this->groupId);
        if ($group === null) {
            return [];
        }

        $memberIds = $group->members->pluck('id');

        $submissions = MetricSubmission::query()
            ->whereIn('user_id', $memberIds)
            ->with(['values', 'user'])
            ->whereBetween('recorded_at', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get();

        $submissions = $this->effectiveSameDayOneRepMaxSubmissions($submissions);

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

            $isProjected = $submission->owner_type === TrainingProgramBlock::class;

            $map[$cellKey]['count']++;
            $map[$cellKey]['entries'][] = [
                'summary' => $metricInstance->summary(),
                'athlete' => $submission->user->name,
                'user_id' => $submission->user_id,
                'submission_id' => $submission->id,
                'colorClass' => $this->metricColorClass($submission->metric, $fieldValues, $metricInstance),
                'isProjected' => $isProjected,
                'data' => MetricSubmissionData::fromModel($submission)->toArray(),
            ];
        }

        return $map;
    }

    private function effectiveSameDayOneRepMaxSubmissions(Collection $submissions): Collection
    {
        $oneRepMaxSubmissions = $submissions
            ->filter(fn (MetricSubmission $submission) => $submission->metric === MetricEnum::OneRepMax)
            ->groupBy(fn (MetricSubmission $submission) => $submission->user_id.'-'.$submission->recorded_at->format('Y-m-d'))
            ->map(function (Collection $sameDaySubmissions): MetricSubmission {
                return $sameDaySubmissions
                    ->filter(fn (MetricSubmission $submission) => $submission->owner_type === null || $submission->owner_type === User::class)
                    ->sortByDesc('id')
                    ->first()
                    ?? $sameDaySubmissions->sortByDesc('id')->first();
            });

        return $submissions
            ->reject(fn (MetricSubmission $submission) => $submission->metric === MetricEnum::OneRepMax)
            ->concat($oneRepMaxSubmissions)
            ->sortBy('id')
            ->values();
    }

    #[Computed]
    public function currentMetricValues(): array
    {
        if ($this->userId === null) {
            return [];
        }

        $result = [];

        foreach (MetricEnum::cases() as $metricCase) {
            $submission = $this->latestCurrentMetricSubmission($this->userId, $metricCase);

            if ($submission) {
                $fieldValues = $submission->values->pluck('value', 'field')->all();
                $metricInstance = $metricCase->metricClass()::from($fieldValues);
                $label = $this->currentMetricDisplayLabel($metricCase, $fieldValues, $metricInstance);
                $result[$metricCase->value] = [
                    'summary' => $label ?? 'N/A',
                    'recorded_at' => $submission->recorded_at->format('d.m.Y'),
                    'data' => MetricSubmissionData::from($submission)->toArray(),
                    'isAvailable' => $label !== null,
                ];
            } else {
                $result[$metricCase->value] = [
                    'summary' => 'N/A',
                    'recorded_at' => null,
                    'data' => null,
                    'isAvailable' => false,
                ];
            }
        }

        return $result;
    }

    #[Computed]
    public function groupCurrentMetricValues(): array
    {
        if ($this->userId !== null) {
            return [];
        }

        $group = UserGroup::with('members')->find($this->groupId);
        if (! $group || $group->members->isEmpty()) {
            return [];
        }

        $result = [];

        foreach (MetricEnum::cases() as $metricCase) {
            $members = [];
            $withValue = 0;

            foreach ($group->members as $member) {
                $submission = $this->latestCurrentMetricSubmission($member->id, $metricCase);

                $label = null;
                if ($submission) {
                    $fieldValues = $submission->values->pluck('value', 'field')->all();
                    $metricInstance = $metricCase->metricClass()::from($fieldValues);
                    $label = $this->currentMetricDisplayLabel($metricCase, $fieldValues, $metricInstance);
                }

                if ($label !== null) {
                    $withValue++;
                }

                $members[] = [
                    'user_id' => $member->id,
                    'name' => $member->name,
                    'label' => $label ?? 'N/A',
                ];
            }

            $result[$metricCase->value] = [
                'members' => $members,
                'total' => count($members),
                'withValue' => $withValue,
            ];
        }

        return $result;
    }

    public function getMetricRowData(string $metricValue): array
    {
        $data = $this->userId !== null ? $this->metricCellData : $this->groupMetricCellData;
        $filtered = [];
        foreach ($data as $key => $value) {
            if (str_starts_with($key, $metricValue.'-')) {
                $date = substr($key, strlen($metricValue) + 1);
                $filtered[$date] = $value;
            }
        }

        return $filtered;
    }

    protected function latestCurrentMetricSubmission(int $userId, MetricEnum $metricCase): ?MetricSubmission
    {
        $query = MetricSubmission::query()
            ->forAthlete($userId)
            ->forMetric($metricCase)
            ->manual()
            ->where('recorded_at', '<=', now()->format('Y-m-d'))
            ->orderByDesc('recorded_at')
            ->with('values');

        if ($metricCase !== MetricEnum::Readiness) {
            return $query->first();
        }

        return $query->get()->first(function (MetricSubmission $submission) use ($metricCase): bool {
            $fieldValues = $submission->values->pluck('value', 'field')->all();
            $metricInstance = $metricCase->metricClass()::from($fieldValues);

            return $this->currentMetricDisplayLabel($metricCase, $fieldValues, $metricInstance) !== null;
        });
    }

    protected function currentMetricDisplayLabel(MetricEnum $metricCase, array $fieldValues, mixed $metricInstance): ?string
    {
        return match ($metricCase) {
            MetricEnum::OneRepMax => $this->oneRepMaxCurrentLabel($metricInstance),
            MetricEnum::HeartRate => $this->heartRateCurrentLabel($fieldValues),
            MetricEnum::Readiness => $this->readinessCurrentLabel($fieldValues, $metricInstance),
        };
    }

    protected function oneRepMaxCurrentLabel(mixed $metricInstance): ?string
    {
        if (! $metricInstance instanceof OneRepMaxMetric) {
            return null;
        }

        if ($metricInstance->measuredWeight === null) {
            return null;
        }

        return $metricInstance->estimatedLabel().'kg';
    }

    protected function heartRateCurrentLabel(array $fieldValues): ?string
    {
        $hrMetric = HeartRateMetric::from($fieldValues);

        if ($hrMetric->heartRate === null) {
            return null;
        }

        $label = $hrMetric->heartRate.' HR';

        if ($hrMetric->anaerobicThreshold !== null) {
            $label .= ' - '.$hrMetric->anaerobicThreshold.'% IAT';
        }

        return $label;
    }

    protected function readinessCurrentLabel(array $fieldValues, mixed $metricInstance): ?string
    {
        $score = isset($fieldValues['readinessScore'])
            ? (float) $fieldValues['readinessScore']
            : ($metricInstance instanceof ReadinessMetric ? $metricInstance->data()->readinessScore() : null);

        return $score !== null ? number_format($score, 1) : null;
    }

    protected function metricColorClass(MetricEnum $metric, array $fieldValues, mixed $metricInstance): ?string
    {
        if ($metric !== MetricEnum::Readiness) {
            return null;
        }

        $trafficLightColor = $fieldValues['trafficLightColor'] ?? ReadinessMetricData::trafficLightColor(
            $metricInstance instanceof ReadinessMetric ? $metricInstance->data()->trafficLight() : null
        );

        return is_string($trafficLightColor) ? ColorPalette::solidClasses($trafficLightColor) : null;
    }

    public function openCurrentMetric(string $metricValue): void
    {
        $current = $this->currentMetricValues[$metricValue] ?? null;

        if (! $current || ! $current['data']) {
            return;
        }

        $payload = app(MetricModalPayloadBuilder::class)->fromExistingData($current['data'], $metricValue);

        $this->dispatch('open-calendar-metric-form', data: $payload['data'], title: $payload['title']);
    }

    public function openGroupCurrentMetricEdit(int $userId, string $metricValue): void
    {
        $metricEnum = MetricEnum::from($metricValue);
        $cutoffDate = now()->format('Y-m-d');

        $query = MetricSubmission::query()
            ->forAthlete($userId)
            ->forMetric($metricEnum)
            ->manual()
            ->where('recorded_at', '<=', $cutoffDate)
            ->orderByDesc('recorded_at')
            ->with('values');

        $submission = $query->first();

        if ($submission) {
            $payload = app(MetricModalPayloadBuilder::class)->fromSubmission($submission, $metricValue);
            $this->dispatch('open-calendar-metric-form', data: $payload['data'], title: $payload['title']);
        } else {
            $payload = app(MetricModalPayloadBuilder::class)->forCreation($metricValue, $cutoffDate, $userId);
            $this->dispatch('open-calendar-metric-form', data: $payload['data'], title: $payload['title']);
        }
    }

    public function openMetricCell(string $metricValue, string $date): void
    {
        if ($this->userId === null) {
            return;
        }

        $key = "{$metricValue}-{$date}";
        $existingData = $this->metricCellData[$key] ?? null;

        if ($existingData) {
            $payload = app(MetricModalPayloadBuilder::class)->fromExistingData($existingData['data'], $metricValue);
        } else {
            $payload = app(MetricModalPayloadBuilder::class)->forCreation($metricValue, $date, $this->userId);
        }

        $this->dispatch('open-calendar-metric-form', data: $payload['data'], title: $payload['title']);
    }

    public function openGroupMetricCell(string $metricValue, string $date, ?int $userId = null, ?int $submissionId = null): void
    {
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
                $payload = app(MetricModalPayloadBuilder::class)->fromExistingData($entry['data'], $metricValue);
                $this->dispatch('open-calendar-metric-form', data: $payload['data'], title: $payload['title']);
            }

            return;
        }

        $group = UserGroup::with('members')->find($this->groupId);
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

        $payload = app(MetricModalPayloadBuilder::class)->forGroupCreation($metricValue, $date, $availableAthletes);
        $this->dispatch('open-calendar-metric-form', data: $payload['data'], title: $payload['title']);
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

        $this->refreshMetricCaches();
    }

    #[On('calendar-metric-form.submitted')]
    public function onMetricFormSubmitted(array $data): void
    {
        if (empty($data['_persisted'])) {
            $metric = MetricEnum::from($data['metric']);
            $metricClass = $metric->metricClass();
            $targetUserId = (int) ($data['user_id'] ?? $this->userId);

            $submission = new MetricSubmissionData(
                id: $data['id'] ?? null,
                user_id: $targetUserId,
                metric: $metric,
                recorded_by: auth()->id(),
                recorded_at: $data['recorded_at'] ?? null,
                data: $metricClass::from($data['data'] ?? []),
            );

            try {
                $submission->persist();
            } catch (DuplicateManualMetricSubmission $exception) {
                Flux::toast(text: __($exception->getMessage()), variant: 'warning');

                return;
            }

            if ($metric === MetricEnum::OneRepMax) {
                $projectedService = app(ProjectedOneRepMaxService::class);
                $projectedService->syncForAthleteBlocks($targetUserId);
            }
        }

        $this->refreshMetricCaches();
    }

    protected function refreshMetricCaches(): void
    {
        unset(
            $this->metricSummaryDates,
            $this->metricCellData,
            $this->currentMetricValues,
            $this->groupMetricCellData,
            $this->groupCurrentMetricValues,
        );

        $this->metricsRenderKey++;
    }

    public function openProgramSlot(int $trainingProgramId, string $date): void
    {
        $payload = app(WeekSlotModalPayloadBuilder::class)->forProgramPrefill(
            $trainingProgramId,
            $date,
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

    public function openAddBlock(): void
    {
        $builder = app(BlockModalPayloadBuilder::class);
        $payload = $builder->forAdd(
            $this->groupId,
            $this->userId,
            $builder->categoryOptions($this->groupedPrograms),
        );

        $this->dispatch('open-block', data: $payload);
    }

    public function openBlock(string $date): void
    {
        $payload = app(BlockModalPayloadBuilder::class)->forDate($date, $this->groupId, $this->userId);

        $this->dispatch('open-block', data: $payload);
    }

    public function editBlock(int $blockId): void
    {
        $block = TrainingProgramBlock::find($blockId);
        if (! $block) {
            return;
        }

        $isCategoryBlock = $block->category_id !== null;
        $isAthleteSpecificBlock = $block->user_id !== null && $block->parent_id === null;

        if ($isCategoryBlock && $this->userId !== null && ! $isAthleteSpecificBlock) {
            $groupBlock = $block->parent_id ? $block->parent : $block;
            $override = $block->parent_id ? $block : $groupBlock->athleteOverride($this->userId);

            $payload = app(BlockModalPayloadBuilder::class)->forEdit(
                $override?->id,
                $this->groupId,
                $this->userId,
                $groupBlock->id,
            );

            $this->dispatch('open-block', data: $payload);

            return;
        }

        $payload = app(BlockModalPayloadBuilder::class)->forEdit(
            $blockId,
            $this->groupId,
            $isCategoryBlock ? null : $this->userId,
        );

        $this->dispatch('open-block', data: $payload);
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

        $payload = app(BlockModalPayloadBuilder::class)->forCategoryDate(
            $date,
            $this->groupId,
            $this->userId,
            $categoryId,
            $tag,
        );

        $this->dispatch('open-block', data: $payload);
    }

    public function openBlockRange(string $startDate, string $endDate): void
    {
        $payload = app(BlockModalPayloadBuilder::class)->forDateRange(
            $startDate,
            $endDate,
            $this->groupId,
            $this->userId,
        );

        $this->dispatch('open-block', data: $payload);
    }

    public function openCategoryBlockRange(string $startDate, string $endDate, int $categoryId): void
    {
        $tag = Tag::find($categoryId);

        $payload = app(BlockModalPayloadBuilder::class)->forCategoryDateRange(
            $startDate,
            $endDate,
            $this->groupId,
            $this->userId,
            $categoryId,
            $tag,
        );

        $this->dispatch('open-block', data: $payload);
    }

    #[On('block.submitted')]
    public function onBlockSubmitted(array $data): void
    {
        if ($this->rejectCategoryOverlap($data)) {
            return;
        }

        DB::transaction(function () use ($data): void {
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
            $batch = $this->createBlockRevisionBatch($groupId, 'save_block');

            if ($parentId !== null) {
                if ($editingBlockId !== null) {
                    $oldBlock = TrainingProgramBlock::find($editingBlockId);
                    if ($oldBlock) {
                        $projectedService->removeForBlock($oldBlock);
                        $this->deleteBlockWithRevision($oldBlock, $batch);
                    }
                }

                $parentBlock = TrainingProgramBlock::find($parentId);
                if ($parentBlock) {
                    $childBlock = $this->createBlockWithRevision([
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
                    ], $batch);

                    $projectedService->syncForBlock($childBlock);
                    $projectedService->syncForBlock($parentBlock->fresh());
                }

                return;
            }

            if ($editingBlockId !== null) {
                $oldBlock = TrainingProgramBlock::find($editingBlockId);
                if ($oldBlock) {
                    $projectedService->removeForBlock($oldBlock);
                }

                if ($isCategoryBlock) {
                    $children = TrainingProgramBlock::where('parent_id', $editingBlockId)->get();
                    foreach ($children as $child) {
                        $projectedService->removeForBlock($child);
                        $this->deleteBlockWithRevision($child, $batch);
                    }
                    if ($oldBlock) {
                        $this->deleteBlockWithRevision($oldBlock, $batch);
                    }
                } elseif ($userId !== null && empty($selectedMembers)) {
                    if ($oldBlock) {
                        $this->deleteBlockWithRevision($oldBlock, $batch);
                    }
                } else {
                    if ($oldBlock) {
                        $matchingBlocks = TrainingProgramBlock::query()
                            ->where('group_id', $groupId)
                            ->where('type', $oldBlock->type)
                            ->where('start', $oldBlock->start)
                            ->where('note', $oldBlock->note)
                            ->get();

                        foreach ($matchingBlocks as $matchingBlock) {
                            $this->deleteBlockWithRevision($matchingBlock, $batch);
                        }
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

            if ($isCategoryBlock && $userId !== null && empty($selectedMembers)) {
                $newBlock = $this->createBlockWithRevision([...$blockData, 'user_id' => $userId], $batch);
                $projectedService->syncForBlock($newBlock);
            } elseif ($isCategoryBlock) {
                $newBlock = $this->createBlockWithRevision([...$blockData, 'user_id' => null], $batch);
                $projectedService->syncForBlock($newBlock);

                $deselectedMembers = $data['deselected_members'] ?? [];
                foreach ($deselectedMembers as $memberId) {
                    $this->createBlockWithRevision([
                        'group_id' => $groupId,
                        'user_id' => $memberId,
                        'parent_id' => $newBlock->id,
                        'category_id' => $categoryId,
                        'type' => $type,
                        'start' => $data['start'],
                        'end' => $data['end'] ?: null,
                        'note' => $data['note'],
                        'color' => $color ?: null,
                        'config' => $config,
                        'active' => false,
                    ], $batch);
                }
            } elseif ($userId !== null && empty($selectedMembers)) {
                $newBlock = $this->createBlockWithRevision([...$blockData, 'user_id' => $userId], $batch);
                $projectedService->syncForBlock($newBlock);
            } else {
                foreach ($selectedMembers as $memberId) {
                    $newBlock = $this->createBlockWithRevision([...$blockData, 'user_id' => $memberId], $batch);
                    $projectedService->syncForBlock($newBlock);
                }
            }
        });

        unset($this->allBlocks, $this->categoryBlocks, $this->visibleMetrics, $this->metricCellData, $this->currentMetricValues, $this->groupMetricCellData, $this->groupCurrentMetricValues);
    }

    protected function rejectCategoryOverlap(array $data): bool
    {
        $categoryId = $data['categoryId'] ?? null;
        if ($categoryId === null) {
            return false;
        }

        $conflict = app(CalendarBlockService::class)->findCategoryOverlap(
            groupId: (int) $data['groupId'],
            categoryId: (int) $categoryId,
            start: Carbon::parse($data['start']),
            end: filled($data['end'] ?? null) ? Carbon::parse($data['end']) : null,
            userId: isset($data['userId']) ? (int) $data['userId'] : null,
            parentId: isset($data['parentId']) ? (int) $data['parentId'] : null,
            excludeBlockId: isset($data['editing_block_id']) ? (int) $data['editing_block_id'] : null,
        );

        if (! $conflict) {
            return false;
        }

        Flux::toast(text: __('Blocks in the same category cannot overlap on the calendar.'), variant: 'danger');

        return true;
    }

    #[On('block.deleted')]
    public function onBlockDeleted(array $data): void
    {
        DB::transaction(function () use ($data): void {
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
            $batch = $this->createBlockRevisionBatch($groupId, 'delete_block');

            if ($existingBlock->parent_id !== null) {
                $projectedService->removeForBlock($existingBlock);
                $this->deactivateBlockWithRevision($existingBlock, $batch);

                $parentBlock = TrainingProgramBlock::find($existingBlock->parent_id);
                if ($parentBlock) {
                    $projectedService->syncForBlock($parentBlock);
                }

                return;
            }

            $projectedService->removeForBlock($existingBlock);

            if ($existingBlock->category_id !== null) {
                $children = TrainingProgramBlock::where('parent_id', $editingBlockId)->get();
                foreach ($children as $child) {
                    $projectedService->removeForBlock($child);
                    $this->deleteBlockWithRevision($child, $batch);
                }
                $this->deleteBlockWithRevision($existingBlock, $batch);
            } elseif ($userId !== null) {
                $this->deleteBlockWithRevision($existingBlock, $batch);
            } else {
                $matchingBlocks = TrainingProgramBlock::query()
                    ->where('group_id', $groupId)
                    ->where('type', $existingBlock->type)
                    ->where('start', $existingBlock->start)
                    ->where('note', $existingBlock->note)
                    ->get();

                foreach ($matchingBlocks as $matchingBlock) {
                    $this->deleteBlockWithRevision($matchingBlock, $batch);
                }
            }
        });

        unset($this->allBlocks, $this->categoryBlocks, $this->visibleMetrics, $this->metricCellData, $this->currentMetricValues, $this->groupMetricCellData, $this->groupCurrentMetricValues);
    }

    protected function createBlockRevisionBatch(int $groupId, string $action)
    {
        $group = UserGroup::findOrFail($groupId);

        return app(TrainingStateRevisionService::class)->createBatch($group, $action);
    }

    protected function createBlockWithRevision(array $attributes, $batch): TrainingProgramBlock
    {
        $block = TrainingProgramBlock::create($attributes + [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        app(TrainingStateRevisionService::class)->recordStateChange(
            batch: $batch,
            subject: $block,
            stateKey: 'block',
            beforeValue: null,
            afterValue: 'created',
            beforePayload: [],
            afterPayload: $this->blockRevisionPayload($block),
        );

        if (is_string($block->note) && trim($block->note) !== '') {
            app(TrainingStateRevisionService::class)->recordStateChange(
                batch: $batch,
                subject: $block,
                stateKey: 'note',
                beforeValue: null,
                afterValue: $block->note,
                beforePayload: [],
                afterPayload: ['note' => $block->note],
            );
        }

        return $block;
    }

    protected function deleteBlockWithRevision(TrainingProgramBlock $block, $batch): void
    {
        app(TrainingStateRevisionService::class)->recordStateChange(
            batch: $batch,
            subject: $block,
            stateKey: 'block',
            beforeValue: $block->active ? 'active' : 'inactive',
            afterValue: 'deleted',
            beforePayload: $this->blockRevisionPayload($block),
            afterPayload: [],
        );

        if (is_string($block->note) && trim($block->note) !== '') {
            app(TrainingStateRevisionService::class)->recordStateChange(
                batch: $batch,
                subject: $block,
                stateKey: 'note',
                beforeValue: $block->note,
                afterValue: null,
                beforePayload: ['note' => $block->note],
                afterPayload: [],
            );
        }

        $block->delete();
    }

    protected function deactivateBlockWithRevision(TrainingProgramBlock $block, $batch): void
    {
        $before = $this->blockRevisionPayload($block);

        $block->forceFill([
            'active' => false,
            'updated_by' => auth()->id(),
        ])->save();

        app(TrainingStateRevisionService::class)->recordStateChange(
            batch: $batch,
            subject: $block->fresh(),
            stateKey: 'block',
            beforeValue: 'active',
            afterValue: 'inactive',
            beforePayload: $before,
            afterPayload: $this->blockRevisionPayload($block->fresh()),
        );
    }

    protected function blockRevisionPayload(TrainingProgramBlock $block): array
    {
        return [
            'group_id' => $block->group_id,
            'user_id' => $block->user_id,
            'parent_id' => $block->parent_id,
            'category_id' => $block->category_id,
            'type' => $block->type?->value ?? (string) $block->type,
            'start' => $block->start?->format('Y-m-d'),
            'end' => $block->end?->format('Y-m-d'),
            'note' => $block->note,
            'color' => $block->color,
            'config' => $block->config?->toArray(),
            'active' => (bool) $block->active,
            'created_by' => $block->created_by,
            'updated_by' => $block->updated_by,
        ];
    }

    public function toggleExerciseDisabled(int $programExerciseId, int $exerciseProgramId): void
    {
        $exerciseProgram = ExerciseProgram::findOrFail($exerciseProgramId);
        $config = $exerciseProgram->config;
        $previousOverrides = $config->exerciseOverrides($programExerciseId, $this->userId);
        $nextOverrides = clone $previousOverrides;

        if ($this->userId !== null) {
            [$planOverrides] = $config->effectiveExerciseOverrides($programExerciseId, $this->userId);
            $currentlyDisabled = EffectiveExerciseConfig::resolveDisabled($planOverrides, $nextOverrides);
            $nextOverrides->disabled = $currentlyDisabled ? false : true;

            $defaultDisabled = $planOverrides->disabled ?? false;
            if ($nextOverrides->disabled === $defaultDisabled) {
                $nextOverrides->disabled = null;
            }

            $config->setExerciseOverrides($programExerciseId, $nextOverrides, $this->userId);
        } else {
            $nextOverrides->disabled = ! ($nextOverrides->disabled ?? false) ?: null;
            $config->setExerciseOverrides($programExerciseId, $nextOverrides);
        }

        $exerciseProgram->config = $config;
        $shouldScopeRebuildToAthlete = $this->userId !== null;

        if ($shouldScopeRebuildToAthlete) {
            $exerciseProgram->saveQuietly();
            app(TrainingSessionRebuildDispatcher::class)
                ->dispatchFutureSlotsForExerciseProgramChange($exerciseProgram->id, $this->userId);
        } else {
            $exerciseProgram->save();
        }

        app(TrainingPlanRevisionService::class)->recordOverrideSettingChanges(
            owner: $exerciseProgram,
            programExerciseId: $programExerciseId,
            userId: $this->userId,
            before: $previousOverrides,
            after: $nextOverrides,
            action: $this->resolvePlanSettingsRevisionAction($previousOverrides, $nextOverrides),
        );

        unset($this->programs, $this->groupedPrograms);
    }

    protected function resolvePlanSettingsRevisionAction(ExerciseOverrides $before, ExerciseOverrides $after): string
    {
        $beforeHasOverrides = $this->hasTrackedSettingOverrides($before);
        $afterHasOverrides = $this->hasTrackedSettingOverrides($after);

        return match (true) {
            ! $beforeHasOverrides && $afterHasOverrides => 'create_setting_overrides',
            $beforeHasOverrides && ! $afterHasOverrides => 'reset_setting_overrides',
            default => 'update_setting_overrides',
        };
    }

    protected function hasTrackedSettingOverrides(ExerciseOverrides $overrides): bool
    {
        foreach ([
            'settings',
            'startsAtDate',
            'sets',
            'reps',
            'weight',
            'tempo',
            'rest',
            'distance',
            'duration',
            'heartRate',
            'heartRateZone',
            'pace',
            'watts',
            'sessionGrouping',
            'disabled',
        ] as $field) {
            if (($overrides->{$field} ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    public function isExerciseDisabled(int $programExerciseId, ExerciseProgram $program): bool
    {
        return $program->config->effectiveDisabled($programExerciseId, $this->userId);
    }

    public function openAddContent(): void
    {
        $this->dispatch('trigger-add-content');
    }

    #[On('programs-changed')]
    public function onProgramsChanged(): void
    {
        unset($this->programs, $this->groupedPrograms);
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

        unset($this->programCellSlots, $this->athleteSlotOrder);

        $this->dispatch('grid-cells-changed');
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

        unset($this->programCellSlots, $this->athleteSlotOrder);

        $this->dispatch('grid-cells-changed');
    }

    public function removeTrainingProgram(int $trainingProgramId): void
    {
        $lockedCount = app(TrainingSessionEditGuard::class)->countImmutableSlotsForTrainingProgram($trainingProgramId);
        if ($lockedCount > 0) {
            Flux::toast(text: app(TrainingSessionEditGuard::class)->immutableProgramMessage($lockedCount), variant: 'danger');

            return;
        }

        $trainingProgram = TrainingProgram::with('program')->findOrFail($trainingProgramId);
        $this->cleanupOrphanedCategoryBlocks($trainingProgram);
        $trainingProgram->delete();
        unset($this->programs, $this->groupedPrograms, $this->categoryBlocks, $this->visibleMetrics);
    }

    public function openEditProgram(int $trainingProgramId): void
    {
        $trainingProgram = TrainingProgram::with('program.exerciseCategory', 'program.exercises')->findOrFail($trainingProgramId);

        $programData = ExerciseProgramData::fromModel($trainingProgram->program);

        $this->editingTrainingProgramId = $trainingProgramId;
        $this->duplicatingTrainingProgramId = null;

        $this->dispatch('open-edit-program', data: [
            ...$programData->toArray(),
            'status' => $trainingProgram->statusValue(),
            '_show_training_program_status' => true,
        ]);
    }

    public function openDuplicateProgram(int $trainingProgramId): void
    {
        $trainingProgram = TrainingProgram::with('program.exerciseCategory', 'program.exercises')->findOrFail($trainingProgramId);

        $programData = ExerciseProgramData::fromModel($trainingProgram->program);

        $this->editingTrainingProgramId = null;
        $this->duplicatingTrainingProgramId = $trainingProgramId;

        $this->dispatch('open-edit-program', data: [
            ...$programData->toArray(),
            'id' => null,
            'name' => '',
            'status' => $trainingProgram->statusValue(),
            '_show_training_program_status' => true,
            '_duplicate_source_training_program_id' => $trainingProgramId,
        ], title: __('Duplicate Program'), focusField: 'name');
    }

    #[On('edit-program.submitted')]
    public function handleEditProgramSubmitted(array $data): void
    {
        if (! empty($data['_duplicate_source_training_program_id'])) {
            $this->handleDuplicateProgramSubmitted($data);

            return;
        }

        if ($this->editingTrainingProgramId === null) {
            return;
        }

        $trainingProgram = TrainingProgram::findOrFail($this->editingTrainingProgramId);

        $programData = ExerciseProgramData::from([
            'id' => $trainingProgram->exercise_program_id,
            ...$data,
        ]);
        $programData->persist();
        $trainingProgram->update([
            'status' => TrainingProgram::normalizeStatus($data['status'] ?? null),
        ]);

        $this->editingTrainingProgramId = null;
        unset($this->programs, $this->groupedPrograms);
    }

    protected function handleDuplicateProgramSubmitted(array $data): void
    {
        $sourceTrainingProgramId = $this->duplicatingTrainingProgramId
            ?? (int) ($data['_duplicate_source_training_program_id'] ?? 0);

        if ($sourceTrainingProgramId <= 0) {
            return;
        }

        $sourceTrainingProgram = TrainingProgram::with('program')->findOrFail($sourceTrainingProgramId);

        DB::transaction(function () use ($sourceTrainingProgram, $data): void {
            $clone = $sourceTrainingProgram->program->duplicate();
            $ownerId = isset($data['owner_id']) && $data['owner_id'] !== '' && (int) $data['owner_id'] > 0
                ? (int) $data['owner_id']
                : null;
            $categoryId = isset($data['exercise_category_id']) && $data['exercise_category_id'] !== ''
                ? (int) $data['exercise_category_id']
                : null;

            $clone->update([
                'name' => $data['name'] ?? '',
                'type' => $data['type'] ?? $clone->type,
                'exercise_category_id' => $categoryId,
                'owner_id' => $ownerId,
                'warm_up_program_id' => null,
                'warm_down_program_id' => null,
            ]);

            $clone->tags()->sync($data['internalTags'] ?? []);

            $maxSort = TrainingProgram::where('group_id', $sourceTrainingProgram->group_id)->max('sort') ?? -1;

            $trainingProgram = TrainingProgram::create([
                'group_id' => $sourceTrainingProgram->group_id,
                'exercise_program_id' => $clone->id,
                'sort' => $maxSort + 1,
                'status' => TrainingProgram::normalizeStatus($data['status'] ?? null),
                'planned_session_count' => $sourceTrainingProgram->planned_session_count,
                'owner_id' => $sourceTrainingProgram->owner_id,
            ]);

            $clone->update([
                'parent_type' => TrainingProgram::class,
                'parent_id' => $trainingProgram->id,
            ]);
        });

        $this->duplicatingTrainingProgramId = null;
        unset($this->programs, $this->groupedPrograms, $this->programCellSlots, $this->athleteSlotOrder);
    }

    #[On('edit-program.delete-requested')]
    public function handleEditProgramDeleteRequested(): void
    {
        Flux::modal('confirm-delete-program')->show();
    }

    public function deleteEditingTrainingProgram(): void
    {
        if ($this->editingTrainingProgramId === null) {
            return;
        }

        $lockedCount = app(TrainingSessionEditGuard::class)->countImmutableSlotsForTrainingProgram($this->editingTrainingProgramId);
        if ($lockedCount > 0) {
            Flux::toast(text: app(TrainingSessionEditGuard::class)->immutableProgramMessage($lockedCount), variant: 'danger');

            return;
        }

        $trainingProgram = TrainingProgram::with('program')->findOrFail($this->editingTrainingProgramId);
        $this->cleanupOrphanedCategoryBlocks($trainingProgram);
        $trainingProgram->delete();

        $this->editingTrainingProgramId = null;
        unset($this->programs, $this->groupedPrograms, $this->categoryBlocks, $this->visibleMetrics);
        Flux::modal('confirm-delete-program')->close();
        Flux::modal('edit-program')->close();
    }

    public function navigateToPlan(int $trainingProgramId): void
    {
        $this->dispatch('navigate-to-plan', trainingProgramId: $trainingProgramId);
    }

    private function cleanupOrphanedCategoryBlocks(TrainingProgram $trainingProgram): void
    {
        $categoryId = $trainingProgram->program?->exercise_category_id;

        if ($categoryId === null) {
            return;
        }

        $otherProgramsExist = TrainingProgram::where('group_id', $trainingProgram->group_id)
            ->where('id', '!=', $trainingProgram->id)
            ->whereHas('program', fn ($q) => $q->where('exercise_category_id', $categoryId))
            ->exists();

        if ($otherProgramsExist) {
            return;
        }

        $blocks = TrainingProgramBlock::where('group_id', $trainingProgram->group_id)
            ->where('category_id', $categoryId)
            ->where('type', TrainingProgramBlockTypeEnum::Category)
            ->get();

        $projectedService = app(ProjectedOneRepMaxService::class);

        foreach ($blocks as $block) {
            $children = TrainingProgramBlock::where('parent_id', $block->id)->get();
            foreach ($children as $child) {
                $projectedService->removeForBlock($child);
            }
            TrainingProgramBlock::where('parent_id', $block->id)->delete();

            $projectedService->removeForBlock($block);
            $block->delete();
        }
    }

    public function render(): View
    {
        return view('livewire.training.calendar-programs-view');
    }
}
