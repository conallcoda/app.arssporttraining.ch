<?php

namespace App\Livewire\Training\Concerns;

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\Metrics\OneRepMaxMetric;
use App\Data\Athlete\Metric\MetricSubmissionData;
use App\Data\Training\Blocks\BlockConfig;
use App\Exceptions\DuplicateManualMetricSubmission;
use App\Models\Athlete\MetricSubmission;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Models\Users\UserGroup;
use App\Support\Profiling\PlanGridProfiler;
use App\Support\Training\BlockModalPayloadBuilder;
use App\Support\Training\MetricModalPayloadBuilder;
use App\Support\Training\SlotStatusPresenter;
use App\Training\CalendarBlockService;
use App\Training\EffectiveOneRepMaxSubmissionResolver;
use App\Training\ProjectedOneRepMaxService;
use App\Training\TrainingSessionEditGuard;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;

trait WithCalendarPlan
{
    public function updatedPlanCategory(): void
    {
        $this->planBlock = 'ungrouped';
        unset($this->planBlockOptions, $this->planProgramOptions);
        $this->selectOverlappingBlock();
        $this->planProgram = '';
        $options = $this->planProgramOptions;
        if ($options->isNotEmpty()) {
            $this->planProgram = (string) $options->keys()->first();
        }
        $this->syncPlanProgramName();
        $this->syncPlanProgramStatus();
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
        $this->syncPlanProgramStatus();
    }

    public function updatedPlanProgram(): void
    {
        $this->syncPlanProgramName();
        $this->syncPlanProgramStatus();
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

    public function savePlanProgramStatus(): void
    {
        $program = $this->planSelectedProgram;
        if (! $program) {
            return;
        }

        $program->update([
            'status' => TrainingProgram::normalizeStatus($this->planProgramStatus),
        ]);

        unset($this->programs, $this->groupedPrograms, $this->planCategoryOptions, $this->planProgramOptions);
        $this->syncPlanProgramName();
        $this->syncPlanProgramStatus();
    }

    public function savePlanSessionCount(): void
    {
        $program = $this->planSelectedProgram;
        if (! $program || $this->user !== '') {
            return;
        }

        $minimum = max(
            1,
            $this->scopedScheduledSessionCount($program),
        );
        $this->planSessionCount = max($minimum, min(520, $this->planSessionCount));
        $this->saveConfiguredPlanSessionCount($program, $this->planSessionCount);

        unset($this->planScheduleInfo);
    }

    protected function syncPlanProgramName(): void
    {
        $program = $this->planSelectedProgram;
        $this->planProgramName = $program?->program->name ?? '';
    }

    protected function syncPlanProgramStatus(): void
    {
        $program = $this->planSelectedProgram;
        $this->planProgramStatus = $program?->statusValue() ?? TrainingProgram::STATUS_ACTIVE;
        $this->planSessionCount = $program === null ? 0 : $this->effectivePlanSessionCount($program);
    }

    protected function effectivePlanSessionCount(TrainingProgram $program, ?int $scheduledSessionCount = null): int
    {
        return max(
            1,
            $this->configuredPlanSessionCount($program),
            $scheduledSessionCount ?? 0,
            $this->scopedScheduledSessionCount($program),
        );
    }

    protected function configuredPlanSessionCount(TrainingProgram $program): int
    {
        if ($this->planBlock === 'ungrouped') {
            return (int) ($program->planned_session_count ?? 0);
        }

        $block = TrainingProgramBlock::find((int) $this->planBlock);

        return (int) ($block?->config?->plannedSessionCounts[$program->id] ?? 0);
    }

    protected function saveConfiguredPlanSessionCount(TrainingProgram $program, int $count): void
    {
        if ($this->planBlock === 'ungrouped') {
            $program->update(['planned_session_count' => $count]);

            return;
        }

        $block = TrainingProgramBlock::find((int) $this->planBlock);
        if (! $block) {
            return;
        }

        $config = $block->config ?? new BlockConfig;
        $config->plannedSessionCounts[$program->id] = $count;
        $block->config = $config;
        $block->save();
    }

    protected function scopedScheduledSessionCount(TrainingProgram $program): int
    {
        $query = TrainingProgramSlot::query()
            ->where('training_program_id', $program->id);

        if ($this->planBlock === 'ungrouped') {
            $this->applyUngroupedFilter($query);
        } else {
            $block = TrainingProgramBlock::find((int) $this->planBlock);
            if ($block) {
                $query->whereBetween('datetime', [
                    $block->start->copy()->startOfDay(),
                    ($block->end ?? $block->start)->copy()->endOfDay(),
                ]);
            }
        }

        return (int) ((clone $query)
            ->selectRaw('user_id, COUNT(*) as session_count')
            ->groupBy('user_id')
            ->pluck('session_count')
            ->max() ?? 0);
    }

    #[On('navigate-to-plan')]
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
        $this->planProgramStatus = $trainingProgram->statusValue();

        unset($this->planBlockOptions, $this->planProgramOptions, $this->planCategoryOptions);

        $slotDate = TrainingProgramSlot::where('training_program_id', $trainingProgramId)
            ->orderBy('datetime')
            ->value('datetime');

        $this->selectOverlappingBlock($slotDate ? Carbon::parse($slotDate) : null);

    }

    protected function selectOverlappingBlock(?Carbon $referenceDate = null): void
    {
        $categoryId = (int) $this->planCategory;
        if ($categoryId === 0 || $this->group === '') {
            return;
        }

        $service = app(CalendarBlockService::class);
        $block = $service->findOverlappingBlock(
            (int) $this->group,
            $this->user !== '' ? (int) $this->user : null,
            $categoryId,
            $referenceDate,
        );

        if ($block) {
            $this->planBlock = (string) $block->id;
        }
    }

    #[Computed]
    public function planCategoryOptions(): Collection
    {
        return PlanGridProfiler::measure('WithCalendarPlan.planCategoryOptions', $this->profileContext(), function (): Collection {
            $grouped = $this->groupedPrograms;

            $options = $grouped->mapWithKeys(function (array $group, int $categoryId) {
                $name = $group['category']?->name ?? __('Uncategorized');

                return [$categoryId => $name];
            });

            if ($options->isNotEmpty() && $this->planCategory === '') {
                $this->planCategory = (string) $options->keys()->first();
            }

            return $options;
        });
    }

    #[Computed]
    public function planBlockOptions(): Collection
    {
        return PlanGridProfiler::measure('WithCalendarPlan.planBlockOptions', $this->profileContext(), function (): Collection {
            $options = collect(['ungrouped' => __('Ungrouped')]);

            if ($this->planCategory === '' || $this->group === '') {
                return $options;
            }

            $categoryId = (int) $this->planCategory;
            if ($categoryId === 0) {
                return $options;
            }

            $service = app(CalendarBlockService::class);
            $blockOptions = $service->buildBlockOptions(
                (int) $this->group,
                $this->user !== '' ? (int) $this->user : null,
                $categoryId,
            );

            return $options->union(collect($blockOptions)->mapWithKeys(fn ($label, $id) => [(string) $id => $label]));
        });
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
            $entry->id => $entry->program->name.($entry->isArchived() ? ' ('.__('Archived').')' : ''),
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
        if (! $block) {
            return null;
        }

        if ($this->user !== '' && $block->category_id !== null) {
            $override = $block->athleteOverride((int) $this->user);
            if ($override) {
                return $override->config?->goal;
            }
        }

        return $block->config?->goal;
    }

    #[Computed]
    public function planHasBlock(): bool
    {
        return $this->planBlock !== 'ungrouped';
    }

    protected function planCutoffDate(): string
    {
        $block = $this->planBlock !== 'ungrouped' ? TrainingProgramBlock::find((int) $this->planBlock) : null;

        return $block?->start?->format('Y-m-d') ?? now()->format('Y-m-d');
    }

    protected function findLatestSubmission(int $userId, MetricEnum $metric, string $cutoffDate): ?MetricSubmission
    {
        if ($metric === MetricEnum::OneRepMax) {
            return app(EffectiveOneRepMaxSubmissionResolver::class)->resolve($userId, $cutoffDate);
        }

        $query = MetricSubmission::query()
            ->forAthlete($userId)
            ->forMetric($metric)
            ->where('recorded_at', '<=', $cutoffDate)
            ->orderByDesc('recorded_at')
            ->with('values');

        $query->manual();

        return $query->first();
    }

    /** @return array{measuredReps: ?int, measuredWeight: ?float} */
    #[Computed]
    public function planMeasuredData(): array
    {
        return PlanGridProfiler::measure('WithCalendarPlan.planMeasuredData', $this->profileContext(), function (): array {
            if ($this->user === '') {
                return ['measuredReps' => 1, 'measuredWeight' => 50];
            }

            if ($this->planBlock === 'ungrouped') {
                return ['measuredReps' => null, 'measuredWeight' => null];
            }

            $submission = $this->findLatestSubmission((int) $this->user, MetricEnum::OneRepMax, $this->planCutoffDate());

            if (! $submission) {
                return ['measuredReps' => null, 'measuredWeight' => null];
            }

            $fieldValues = $submission->values->pluck('value', 'field')->all();
            $metric = MetricEnum::OneRepMax->metricClass()::from($fieldValues);

            return [
                'measuredReps' => $metric->measuredReps,
                'measuredWeight' => $metric->measuredWeight,
            ];
        });
    }

    /** @return array{maxHR: ?int, iatPercent: ?int} */
    #[Computed]
    public function planHeartRateData(): array
    {
        return PlanGridProfiler::measure('WithCalendarPlan.planHeartRateData', $this->profileContext(), function (): array {
            if ($this->user === '') {
                return ['maxHR' => null, 'iatPercent' => null];
            }

            $submission = $this->findLatestSubmission((int) $this->user, MetricEnum::HeartRate, $this->planCutoffDate());

            if (! $submission) {
                return ['maxHR' => null, 'iatPercent' => null];
            }

            $fieldValues = $submission->values->pluck('value', 'field')->all();
            $metric = MetricEnum::HeartRate->metricClass()::from($fieldValues);

            return [
                'maxHR' => $metric->heartRate,
                'iatPercent' => $metric->anaerobicThreshold,
            ];
        });
    }

    #[Computed]
    public function planHasAutoWeightExercises(): bool
    {
        $program = $this->planSelectedProgram;
        if (! $program) {
            return false;
        }

        foreach ($program->program->exercises->filter(fn ($exercise) => ($exercise->pivot->type ?? 'main') === 'main') as $exercise) {
            $config = $exercise->config;
            if (in_array('weight', $config->settings ?? [])
                && ($config->weight?->mode ?? 'manual') === 'automatic') {
                return true;
            }
        }

        return false;
    }

    #[Computed]
    public function planHasHeartRateExercises(): bool
    {
        $program = $this->planSelectedProgram;
        if (! $program) {
            return false;
        }

        foreach ($program->program->exercises->filter(fn ($exercise) => ($exercise->pivot->type ?? 'main') === 'main') as $exercise) {
            $config = $exercise->config;
            $settings = $config->settings ?? [];
            if (in_array('heartRate', $settings) || in_array('heartRateZone', $settings)) {
                return true;
            }
        }

        return false;
    }

    #[Computed]
    public function plan1rmLabel(): ?string
    {
        $data = $this->planMeasuredData;
        if ($data['measuredWeight'] === null) {
            return null;
        }

        $metric = new OneRepMaxMetric(
            measuredReps: $data['measuredReps'],
            measuredWeight: $data['measuredWeight'],
        );

        return $metric->summary();
    }

    #[Computed]
    public function planHeartRateLabel(): ?string
    {
        $data = $this->planHeartRateData;
        if ($data['maxHR'] === null) {
            return null;
        }

        $label = $data['maxHR'].' HR';
        if ($data['iatPercent'] !== null) {
            $label .= ' - '.$data['iatPercent'].'% IAT';
        }

        return $label;
    }

    #[Renderless]
    public function openPlanBlockEdit(): void
    {
        if (! $this->planHasBlock) {
            return;
        }

        $block = TrainingProgramBlock::find((int) $this->planBlock);
        if (! $block) {
            return;
        }

        $isCategoryBlock = $block->category_id !== null;
        $isAthleteSpecificBlock = $block->user_id !== null && $block->parent_id === null;

        if ($isCategoryBlock && $this->user !== '' && ! $isAthleteSpecificBlock) {
            $groupBlock = $block->parent_id ? $block->parent : $block;
            $override = $block->parent_id ? $block : $groupBlock->athleteOverride((int) $this->user);

            $payload = app(BlockModalPayloadBuilder::class)->forEdit(
                $override?->id,
                $this->group !== '' ? (int) $this->group : 0,
                (int) $this->user,
                $groupBlock->id,
            );

            $this->dispatch('open-block', data: $payload);

            return;
        }

        $payload = app(BlockModalPayloadBuilder::class)->forEdit(
            $block->id,
            $this->group !== '' ? (int) $this->group : 0,
            $isCategoryBlock ? null : ($this->user !== '' ? (int) $this->user : null),
        );

        $this->dispatch('open-block', data: $payload);
    }

    #[Renderless]
    public function openPlan1rmEdit(): void
    {
        if ($this->user === '') {
            return;
        }

        $this->openPlanMetricEdit((int) $this->user, MetricEnum::OneRepMax->value);
    }

    #[Renderless]
    public function openPlanHeartRateEdit(): void
    {
        if ($this->user === '') {
            return;
        }

        $this->openPlanMetricEdit((int) $this->user, MetricEnum::HeartRate->value);
    }

    #[Computed]
    public function planGroupMemberMetrics(): array
    {
        $span = PlanGridProfiler::start('WithCalendarPlan.planGroupMemberMetrics', $this->profileContext());

        try {
            if ($this->user !== '' || $this->group === '') {
                return ['oneRepMax' => [], 'heartRate' => []];
            }

            $group = UserGroup::with('members')->find((int) $this->group);
            if (! $group) {
                return ['oneRepMax' => [], 'heartRate' => []];
            }

            $cutoffDate = $this->planCutoffDate();
            $memberIds = $group->members->pluck('id');

            $ormSubmissions = MetricSubmission::query()
                ->whereIn('user_id', $memberIds)
                ->forMetric(MetricEnum::OneRepMax)
                ->where('recorded_at', '<=', $cutoffDate)
                ->tap(fn (Builder $query) => app(EffectiveOneRepMaxSubmissionResolver::class)->applyOrdering($query))
                ->with('values')
                ->get()
                ->groupBy('user_id')
                ->map->first();

            $hrSubmissions = MetricSubmission::query()
                ->whereIn('user_id', $memberIds)
                ->forMetric(MetricEnum::HeartRate)
                ->manual()
                ->where('recorded_at', '<=', $cutoffDate)
                ->orderByDesc('recorded_at')
                ->with('values')
                ->get()
                ->groupBy('user_id')
                ->map->first();

            $oneRepMax = [];
            $heartRate = [];
            $ormMetricClass = MetricEnum::OneRepMax->metricClass();
            $hrMetricClass = MetricEnum::HeartRate->metricClass();

            foreach ($group->members as $member) {
                $ormLabel = null;
                $ormSubmission = $ormSubmissions->get($member->id);
                if ($ormSubmission) {
                    $fieldValues = $ormSubmission->values->pluck('value', 'field')->all();
                    $metric = $ormMetricClass::from($fieldValues);
                    if ($metric->measuredWeight !== null) {
                        $ormLabel = $metric->estimatedLabel().'kg';
                    }
                }

                $oneRepMax[] = [
                    'user_id' => $member->id,
                    'name' => $member->name,
                    'label' => $ormLabel,
                ];

                $hrLabel = null;
                $hrSubmission = $hrSubmissions->get($member->id);
                if ($hrSubmission) {
                    $fieldValues = $hrSubmission->values->pluck('value', 'field')->all();
                    $hrMetric = $hrMetricClass::from($fieldValues);
                    if ($hrMetric->heartRate !== null) {
                        $hrLabel = $hrMetric->heartRate.' HR';
                        if ($hrMetric->anaerobicThreshold !== null) {
                            $hrLabel .= ' - '.$hrMetric->anaerobicThreshold.'% IAT';
                        }
                    }
                }

                $heartRate[] = [
                    'user_id' => $member->id,
                    'name' => $member->name,
                    'label' => $hrLabel,
                ];
            }

            return ['oneRepMax' => $oneRepMax, 'heartRate' => $heartRate];
        } finally {
            PlanGridProfiler::end($span, [
                'one_rep_max_count' => isset($oneRepMax) ? count($oneRepMax) : null,
                'heart_rate_count' => isset($heartRate) ? count($heartRate) : null,
            ]);
        }
    }

    #[Renderless]
    public function openPlanMetricEdit(int $userId, string $metric): void
    {
        $metricEnum = MetricEnum::from($metric);
        $cutoffDate = $this->planCutoffDate();
        $submission = $this->findLatestSubmission($userId, $metricEnum, $cutoffDate);

        if ($submission) {
            $payload = app(MetricModalPayloadBuilder::class)->fromSubmission($submission, $metric);
            $this->dispatch('open-calendar-metric-form', data: $payload['data'], title: $payload['title']);
        } else {
            $payload = app(MetricModalPayloadBuilder::class)->forCreation($metric, $cutoffDate, $userId);
            $this->dispatch('open-calendar-metric-form', data: $payload['data'], title: $payload['title']);
        }
    }

    #[Renderless]
    public function openPlanGroupMemberMetricEdit(int $userId, string $metric): void
    {
        $this->openPlanMetricEdit($userId, $metric);
    }

    #[Renderless]
    public function openPlanGroupMetricAdd(string $metric): void
    {
        if ($this->group === '') {
            return;
        }

        $metricEnum = MetricEnum::from($metric);

        $group = UserGroup::with('members')->find((int) $this->group);
        if (! $group) {
            return;
        }

        $cutoffDate = $this->planCutoffDate();

        $existingUserIds = MetricSubmission::query()
            ->whereIn('user_id', $group->members->pluck('id'))
            ->forMetric($metricEnum)
            ->where('recorded_at', '<=', $cutoffDate)
            ->when($metricEnum === MetricEnum::HeartRate, fn ($q) => $q->manual())
            ->distinct()
            ->pluck('user_id')
            ->all();

        $availableAthletes = $group->members
            ->reject(fn ($member) => in_array($member->id, $existingUserIds, true))
            ->map(fn ($member) => ['id' => $member->id, 'name' => $member->name])
            ->values()
            ->all();

        $payload = app(MetricModalPayloadBuilder::class)->forGroupCreation($metric, $cutoffDate, $availableAthletes);
        $this->dispatch('open-calendar-metric-form', data: $payload['data'], title: $payload['title']);
    }

    #[Computed]
    public function planSelectedProgram(): ?TrainingProgram
    {
        return PlanGridProfiler::measure('WithCalendarPlan.planSelectedProgram', $this->profileContext(), function (): ?TrainingProgram {
            if ($this->planProgram === '') {
                return null;
            }

            return TrainingProgram::with('program.exerciseCategory', 'program.exercises')
                ->find((int) $this->planProgram);
        });
    }

    #[Computed]
    public function planScheduleInfo(): array
    {
        $span = PlanGridProfiler::start('WithCalendarPlan.planScheduleInfo', $this->profileContext());

        try {
            if ($this->planProgram === '') {
                return $this->emptyPlanScheduleInfo();
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

            $editGuard = app(TrainingSessionEditGuard::class);
            $slotRows = (clone $slotQuery)
                ->select([
                    'id',
                    'user_id',
                    'datetime',
                    'scheduled_date',
                    'session_index',
                    'status',
                    'completed_at',
                    'exercise_count',
                    'has_any_modification',
                    'completed_exercise_count',
                    'partial_exercise_count',
                    'skipped_exercise_count',
                    'pending_exercise_count',
                ])
                ->withCount([
                    'exercises as child_exercise_count',
                    'exercises as child_completed_exercise_count' => fn (Builder $query): Builder => $query->where('status', TrainingProgramSlotExerciseStatusEnum::Completed),
                    'exercises as child_partial_exercise_count' => fn (Builder $query): Builder => $query->where('status', TrainingProgramSlotExerciseStatusEnum::PartiallyCompleted),
                    'exercises as child_skipped_exercise_count' => fn (Builder $query): Builder => $query->where('status', TrainingProgramSlotExerciseStatusEnum::Skipped),
                ])
                ->withExists([
                    'exercises as has_recorded_exercise_rows' => fn (Builder $query): Builder => $editGuard->applyRecordedExerciseOutcomeConstraints($query),
                ])
                ->orderBy('datetime')
                ->orderBy('id')
                ->get();
            $statusPresenter = app(SlotStatusPresenter::class);

            return $this->logicalPlanScheduleInfo($slotRows, $editGuard, $statusPresenter);
        } finally {
            PlanGridProfiler::end($span);
        }
    }

    public function planEditorRenderKey(): string
    {
        return 'plan-editor-'.hash('sha256', serialize([
            'program' => $this->planProgram,
            'block' => $this->planBlock,
            'user' => $this->user,
            'schedule' => $this->planScheduleInfo,
        ]));
    }

    protected function emptyPlanScheduleInfo(): array
    {
        return [
            'weeks' => 0,
            'sessionsPerWeek' => 1,
            'scheduled' => false,
            'weekLabels' => [],
            'weekSessions' => [],
            'weekSessionDates' => [],
            'weekSessionDateRanges' => [],
            'expandedWeeks' => [],
            'lockedSessionsByWeek' => [],
            'sessionStatusesByWeek' => [],
            'exerciseSessionStatusesByWeek' => [],
            'calendarWeekSchedule' => [],
        ];
    }

    protected function logicalPlanScheduleInfo(
        Collection $slotRows,
        TrainingSessionEditGuard $editGuard,
        SlotStatusPresenter $statusPresenter,
    ): array {
        foreach ($slotRows->groupBy('user_id') as $athleteSlots) {
            foreach ($athleteSlots->sortBy([['datetime', 'asc'], ['id', 'asc']])->values() as $fallbackIndex => $slot) {
                $slot->session_index = $fallbackIndex;
            }
        }

        $slotsBySessionIndex = $slotRows
            ->groupBy(fn (TrainingProgramSlot $slot): int => (int) $slot->session_index);
        $scheduledSessionCount = $slotsBySessionIndex->isEmpty()
            ? 0
            : ((int) $slotsBySessionIndex->keys()->max()) + 1;
        $plannedSessionCount = $this->planSelectedProgram === null
            ? 0
            : $this->effectivePlanSessionCount($this->planSelectedProgram, $scheduledSessionCount);

        if ($plannedSessionCount === 0) {
            return $this->emptyPlanScheduleInfo();
        }

        $slotStatusValuesById = $slotRows
            ->mapWithKeys(fn (TrainingProgramSlot $slot): array => [
                (int) $slot->id => $statusPresenter->valueForSlotProgress($slot),
            ]);
        $exerciseRowsByProgramExercise = $slotRows->isEmpty()
            ? collect()
            : TrainingProgramSlotExercise::query()
                ->select([
                    'training_program_slot_id',
                    'exercise_program_exercise_id',
                    'status',
                ])
                ->whereIn('training_program_slot_id', $slotRows->pluck('id'))
                ->whereNotNull('exercise_program_exercise_id')
                ->get()
                ->groupBy(fn (TrainingProgramSlotExercise $exercise): string => (string) $exercise->exercise_program_exercise_id);
        $programExerciseIds = $this->planSelectedProgram?->program?->exercises
            ?->map(fn ($exercise): int => (int) $exercise->pivot->id)
            ->values()
            ?? collect();
        $unscheduledStatus = $this->user === '' ? null : $this->unscheduledPlanStatus();

        $weekLabels = [];
        $weekSessions = [];
        $weekSessionDates = [];
        $weekSessionDateRanges = [];
        $lockedSessionsByWeek = [];
        $sessionStatusesByWeek = [];
        $exerciseSessionStatusesByWeek = [];

        for ($sessionIndex = 0; $sessionIndex < $plannedSessionCount; $sessionIndex++) {
            /** @var Collection<int, TrainingProgramSlot> $sessionSlots */
            $sessionSlots = $slotsBySessionIndex->get($sessionIndex, collect());
            $dates = $sessionSlots
                ->map(fn (TrainingProgramSlot $slot): string => ($slot->scheduled_date ?? $slot->datetime)->format('Y-m-d'))
                ->unique()
                ->sort()
                ->values();
            $startDate = $dates->first();
            $endDate = $dates->last();
            $statusValues = $sessionSlots
                ->map(fn (TrainingProgramSlot $slot): ?string => $slotStatusValuesById[(int) $slot->id] ?? null)
                ->filter()
                ->values()
                ->all();

            $weekLabels[$sessionIndex] = 'Session '.($sessionIndex + 1);
            $weekSessions[$sessionIndex] = 1;
            $weekSessionDates[$sessionIndex] = [is_string($startDate) ? $startDate : ''];
            if (is_string($startDate) && is_string($endDate)) {
                $weekSessionDateRanges[$sessionIndex][0] = [
                    'start' => $startDate,
                    'end' => $endDate,
                ];
            }
            $lockedSessionsByWeek[$sessionIndex] = [
                $sessionSlots->contains(fn (TrainingProgramSlot $slot): bool => $editGuard->aggregateColumnsIndicateRecordedOutcome($slot)),
            ];
            $sessionStatusesByWeek[$sessionIndex] = [
                $statusValues === []
                    ? $unscheduledStatus
                    : $this->presentAggregatedStatus($statusValues, $statusPresenter),
            ];

            $slotIdLookup = $sessionSlots->pluck('id')->mapWithKeys(fn (mixed $id): array => [(int) $id => true]);
            foreach ($programExerciseIds as $programExerciseId) {
                $exerciseRows = $exerciseRowsByProgramExercise->get((string) $programExerciseId, collect());
                $exerciseStatuses = $exerciseRows
                    ->filter(fn (TrainingProgramSlotExercise $exercise): bool => isset($slotIdLookup[(int) $exercise->training_program_slot_id]))
                    ->map(fn (TrainingProgramSlotExercise $exercise): string => $exercise->status?->value ?? (string) $exercise->status)
                    ->values()
                    ->all();
                $status = $exerciseStatuses === []
                    ? $unscheduledStatus
                    : $this->presentAggregatedStatus($exerciseStatuses, $statusPresenter, partialAsPending: true);

                $exerciseSessionStatusesByWeek['program-exercise-'.$programExerciseId][$sessionIndex] = [$status];
            }
        }

        return [
            'weeks' => $plannedSessionCount,
            'sessionsPerWeek' => 1,
            'scheduled' => true,
            'weekLabels' => $weekLabels,
            'weekSessions' => $weekSessions,
            'weekSessionDates' => $weekSessionDates,
            'weekSessionDateRanges' => $weekSessionDateRanges,
            'expandedWeeks' => [],
            'lockedSessionsByWeek' => $lockedSessionsByWeek,
            'sessionStatusesByWeek' => $sessionStatusesByWeek,
            'exerciseSessionStatusesByWeek' => $exerciseSessionStatusesByWeek,
            'calendarWeekSchedule' => $this->groupCalendarWeekScheduleInfo(
                $slotRows,
                $exerciseRowsByProgramExercise,
                $editGuard,
                $statusPresenter,
                $slotStatusValuesById,
            ),
        ];
    }

    /** @return array{value: string, label: string, color: array{light: string, dark: string}} */
    protected function unscheduledPlanStatus(): array
    {
        return [
            'value' => 'unscheduled',
            'label' => 'Unscheduled',
            'color' => [
                'light' => '212 212 216',
                'dark' => '113 113 122',
            ],
        ];
    }

    protected function groupCalendarWeekScheduleInfo(
        Collection $slotRows,
        Collection $exerciseRowsByProgramExercise,
        TrainingSessionEditGuard $editGuard,
        SlotStatusPresenter $statusPresenter,
        Collection $slotStatusValuesById,
    ): array {
        if ($slotRows->isEmpty()) {
            return [];
        }

        $slotsByWeek = $slotRows
            ->groupBy(fn (TrainingProgramSlot $slot): string => Carbon::parse($slot->datetime)->startOfWeek()->toDateString())
            ->sortKeys();
        $weekLabels = [];
        $weekSessions = [];
        $weekSessionDates = [];
        $weekSessionDateRanges = [];
        $lockedSessionsByWeek = [];
        $sessionStatusesByWeek = [];
        $exerciseSessionStatusesByWeek = [];

        foreach ($slotsByWeek->values() as $weekIndex => $weekSlots) {
            $weekStart = Carbon::parse($weekSlots->first()->datetime)->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();
            $slotsByPosition = collect();

            foreach ($weekSlots->groupBy('user_id') as $athleteSlots) {
                foreach ($athleteSlots->sortBy([['datetime', 'asc'], ['id', 'asc']])->values() as $position => $slot) {
                    $slotsByPosition->push(['position' => $position, 'slot' => $slot]);
                }
            }

            $slotsByPosition = $slotsByPosition
                ->groupBy('position')
                ->map(fn (Collection $rows): Collection => $rows->pluck('slot'))
                ->sortKeys();
            $weekLabels[$weekIndex] = 'W'.$weekStart->isoWeek().', '.$weekStart->isoWeekYear()
                .'<br><span class="text-[10px] font-normal text-zinc-400 dark:text-zinc-500">'
                .$weekStart->format('d.m').' - '.$weekEnd->format('d.m').'</span>';
            $weekSessions[$weekIndex] = $slotsByPosition->count();

            foreach ($slotsByPosition->values() as $sessionIndex => $sessionSlots) {
                $dates = $sessionSlots
                    ->map(fn (TrainingProgramSlot $slot): string => ($slot->scheduled_date ?? $slot->datetime)->format('Y-m-d'))
                    ->unique()
                    ->sort()
                    ->values();
                $startDate = (string) $dates->first();
                $endDate = (string) $dates->last();
                $statuses = $sessionSlots
                    ->map(fn (TrainingProgramSlot $slot): ?string => $slotStatusValuesById[(int) $slot->id] ?? null)
                    ->filter()
                    ->values()
                    ->all();
                $slotIdLookup = $sessionSlots->pluck('id')->mapWithKeys(fn (mixed $id): array => [(int) $id => true]);

                $weekSessionDates[$weekIndex][$sessionIndex] = $startDate;
                $weekSessionDateRanges[$weekIndex][$sessionIndex] = [
                    'start' => $startDate,
                    'end' => $endDate,
                ];
                $lockedSessionsByWeek[$weekIndex][$sessionIndex] = $sessionSlots->contains(
                    fn (TrainingProgramSlot $slot): bool => $editGuard->aggregateColumnsIndicateRecordedOutcome($slot),
                );
                $sessionStatusesByWeek[$weekIndex][$sessionIndex] = $statuses === []
                    ? null
                    : $this->presentAggregatedStatus($statuses, $statusPresenter);

                foreach ($exerciseRowsByProgramExercise as $programExerciseId => $exerciseRows) {
                    $exerciseStatuses = $exerciseRows
                        ->filter(fn (TrainingProgramSlotExercise $exercise): bool => isset($slotIdLookup[(int) $exercise->training_program_slot_id]))
                        ->map(fn (TrainingProgramSlotExercise $exercise): string => $exercise->status?->value ?? (string) $exercise->status)
                        ->values()
                        ->all();
                    $exerciseSessionStatusesByWeek['program-exercise-'.$programExerciseId][$weekIndex][$sessionIndex] = $exerciseStatuses === []
                        ? null
                        : $this->presentAggregatedStatus($exerciseStatuses, $statusPresenter, partialAsPending: true);
                }
            }
        }

        return [
            'weeks' => $slotsByWeek->count(),
            'sessionsPerWeek' => max(1, (int) collect($weekSessions)->max()),
            'weekLabels' => $weekLabels,
            'weekSessions' => $weekSessions,
            'weekSessionDates' => $weekSessionDates,
            'weekSessionDateRanges' => $weekSessionDateRanges,
            'expandedWeeks' => [],
            'lockedSessionsByWeek' => $lockedSessionsByWeek,
            'sessionStatusesByWeek' => $sessionStatusesByWeek,
            'exerciseSessionStatusesByWeek' => $exerciseSessionStatusesByWeek,
        ];
    }

    /** @param list<string> $statuses */
    protected function presentAggregatedStatus(
        array $statuses,
        SlotStatusPresenter $statusPresenter,
        bool $partialAsPending = false,
    ): array {
        $value = $statusPresenter->aggregateValue($statuses);
        if ($partialAsPending && $value === TrainingProgramSlotStatusEnum::PartiallyCompleted->value) {
            $value = TrainingProgramSlotStatusEnum::Pending->value;
        }

        return [
            'value' => $value,
            'label' => $statusPresenter->label($value),
            'color' => $statusPresenter->color($value),
        ];
    }

    protected function getActiveBlockDateRanges(): array
    {
        if ($this->planCategory === '' || $this->group === '') {
            return [];
        }

        return app(CalendarBlockService::class)->getBlockDateRanges(
            (int) $this->group,
            $this->user !== '' ? (int) $this->user : null,
            (int) $this->planCategory,
        );
    }

    protected function applyUngroupedFilter($query): void
    {
        $ranges = $this->getActiveBlockDateRanges();
        foreach ($ranges as [$start, $end]) {
            $query->whereNotBetween('datetime', [$start, $end]);
        }
    }

    public ?int $pendingPlanMetricDeleteId = null;

    #[On('calendar-metric-form.delete-requested')]
    public function onPlanMetricDeleteRequested(array $data): void
    {
        if (! property_exists($this, 'view') || $this->view !== 'plan') {
            return;
        }

        $id = $data['id'] ?? null;
        if (! $id) {
            return;
        }

        $this->pendingPlanMetricDeleteId = (int) $id;

        Flux::modal('calendar-metric-form')->close();
        Flux::modal('confirm-delete-plan-metric')->show();
    }

    public function deletePlanMetricSubmission(): void
    {
        if (empty($this->pendingPlanMetricDeleteId)) {
            return;
        }

        $submission = MetricSubmission::find($this->pendingPlanMetricDeleteId);
        $this->pendingPlanMetricDeleteId = null;

        Flux::modal('confirm-delete-plan-metric')->close();

        if ($submission) {
            $submission->delete();
        }

        unset($this->planMeasuredData, $this->planHeartRateData, $this->plan1rmLabel, $this->planHeartRateLabel, $this->planGroupMemberMetrics);
    }

    #[On('calendar-metric-form.submitted')]
    public function onPlanMetricFormSubmitted(array $data): void
    {
        if (! property_exists($this, 'view') || $this->view !== 'plan') {
            return;
        }

        if (empty($data['_persisted'])) {
            $metric = MetricEnum::from($data['metric']);
            $metricClass = $metric->metricClass();
            $targetUserId = (int) ($data['user_id'] ?? ($this->user !== '' ? (int) $this->user : 0));

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
                app(ProjectedOneRepMaxService::class)->syncForAthleteBlocks($targetUserId);
            }
        }

        unset($this->planMeasuredData, $this->planHeartRateData, $this->plan1rmLabel, $this->planHeartRateLabel, $this->planGroupMemberMetrics);
    }

    #[On('block.submitted')]
    public function onPlanBlockSubmitted(array $data): void
    {
        if (! property_exists($this, 'view') || $this->view !== 'plan') {
            return;
        }

        $categoryId = $data['categoryId'] ?? null;
        if ($categoryId !== null) {
            $editingBlockId = isset($data['editing_block_id']) ? (int) $data['editing_block_id'] : null;
            $parentId = isset($data['parentId']) ? (int) $data['parentId'] : null;

            $conflict = app(CalendarBlockService::class)->findNewCategoryOverlap(
                groupId: (int) $data['groupId'],
                categoryId: (int) $categoryId,
                start: Carbon::parse($data['start']),
                end: filled($data['end'] ?? null) ? Carbon::parse($data['end']) : null,
                userId: isset($data['userId']) ? (int) $data['userId'] : null,
                parentId: $parentId,
                excludeBlockId: $editingBlockId,
                currentBlockId: $editingBlockId ?? $parentId,
            );

            if ($conflict) {
                Flux::toast(text: __('Blocks in the same category cannot overlap on the calendar.'), variant: 'danger');

                return;
            }
        }

        $editingBlockId = $data['editing_block_id'] ?? null;
        $parentId = $data['parentId'] ?? null;
        $userId = $data['userId'] ?? null;
        $groupId = $data['groupId'];
        $config = $data['config'] ?? null;
        $projectedService = app(ProjectedOneRepMaxService::class);

        if ($parentId !== null) {
            if ($editingBlockId !== null) {
                $block = TrainingProgramBlock::find($editingBlockId);
                if ($block) {
                    $block->update([
                        'start' => $data['start'],
                        'end' => $data['end'] ?: null,
                        'note' => $data['note'],
                        'color' => $data['color'] ?: null,
                        'config' => $config,
                    ]);
                    $projectedService->syncForBlock($block->fresh());
                }
            } else {
                $parentBlock = TrainingProgramBlock::find($parentId);
                if ($parentBlock) {
                    if (app(CalendarBlockService::class)->shouldSyncSingleAthleteParentDates($groupId, $userId)) {
                        $projectedService->removeForBlock($parentBlock);
                        $parentBlock->update([
                            'start' => $data['start'],
                            'end' => $data['end'] ?: null,
                        ]);
                    }

                    $childBlock = TrainingProgramBlock::create([
                        'group_id' => $groupId,
                        'user_id' => $userId,
                        'parent_id' => $parentId,
                        'category_id' => $parentBlock->category_id,
                        'type' => $parentBlock->type,
                        'start' => $data['start'],
                        'end' => $data['end'] ?: null,
                        'note' => $data['note'],
                        'color' => $data['color'] ?: null,
                        'config' => $config,
                        'active' => true,
                    ]);
                    $projectedService->syncForBlock($childBlock);
                    $projectedService->syncForBlock($parentBlock->fresh());
                }
            }
        } elseif ($editingBlockId !== null) {
            $block = TrainingProgramBlock::find($editingBlockId);
            if ($block) {
                $block->update([
                    'start' => $data['start'],
                    'end' => $data['end'] ?: null,
                    'note' => $data['note'],
                    'color' => $data['color'] ?: null,
                    'config' => $config,
                ]);
                $projectedService->syncForBlock($block->fresh());

                $children = TrainingProgramBlock::where('parent_id', $editingBlockId)->get();
                foreach ($children as $child) {
                    $projectedService->syncForBlock($child);
                }
            }
        }

        unset($this->planBlockGoal, $this->planHasBlock, $this->planMeasuredData, $this->planHeartRateData, $this->planGroupMemberMetrics, $this->planBlockOptions);
    }

    #[On('block.deleted')]
    public function onPlanBlockDeleted(array $data): void
    {
        if (! property_exists($this, 'view') || $this->view !== 'plan') {
            return;
        }

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

            unset($this->planBlockGoal, $this->planMeasuredData, $this->planHeartRateData, $this->planGroupMemberMetrics);

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

        unset($this->planBlockGoal, $this->planHasBlock, $this->planBlockOptions, $this->planMeasuredData, $this->planHeartRateData, $this->planGroupMemberMetrics);
    }
}
