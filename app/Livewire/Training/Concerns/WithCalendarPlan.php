<?php

namespace App\Livewire\Training\Concerns;

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\Metrics\HeartRateMetric;
use App\Data\Athlete\Metric\Metrics\OneRepMaxMetric;
use App\Models\Athlete\MetricSubmission;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramSlot;
use App\Training\CalendarBlockService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

trait WithCalendarPlan
{
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

        $this->selectOverlappingBlock();

    }

    protected function selectOverlappingBlock(): void
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
        );

        if ($block) {
            $this->planBlock = (string) $block->id;
        }
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

        $service = app(CalendarBlockService::class);
        $blockOptions = $service->buildBlockOptions(
            (int) $this->group,
            $this->user !== '' ? (int) $this->user : null,
            $categoryId,
        );

        return $options->merge($blockOptions);
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
    public function planHasHeartRateExercises(): bool
    {
        $program = $this->planSelectedProgram;
        if (! $program) {
            return false;
        }

        foreach ($program->program->exercises as $exercise) {
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

        $weight = rtrim(rtrim(number_format($data['measuredWeight'], 1), '0'), '.');
        $reps = $data['measuredReps'] ?? 1;

        return "{$weight}kg ({$reps}x{$weight}kg)";
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

            $this->dispatch('open-block', data: [
                'blockId' => $override?->id,
                'parentId' => $groupBlock->id,
                'groupId' => $this->group !== '' ? (int) $this->group : null,
                'userId' => (int) $this->user,
            ]);

            return;
        }

        $this->dispatch('open-block', data: [
            'blockId' => $block->id,
            'groupId' => $this->group !== '' ? (int) $this->group : null,
            'userId' => $isCategoryBlock ? null : ($this->user !== '' ? (int) $this->user : null),
        ]);
    }

    public function openPlan1rmEdit(): void
    {
        if ($this->user === '') {
            return;
        }

        $data = $this->planMeasuredData;

        if ($data['measuredWeight'] !== null) {
            $this->dispatch('open-calendar-metric-form', data: [
                'metric' => MetricEnum::OneRepMax->value,
                'recorded_at' => now()->format('Y-m-d'),
                'user_id' => (int) $this->user,
            ], title: __('Edit Metric').' ('.MetricEnum::OneRepMax->label().')');
        } else {
            $block = $this->planBlock !== 'ungrouped' ? TrainingProgramBlock::find((int) $this->planBlock) : null;
            $recordedAt = $block?->start?->format('Y-m-d') ?? now()->format('Y-m-d');

            $this->dispatch('open-calendar-metric-form', data: [
                'metric' => MetricEnum::OneRepMax->value,
                'recorded_at' => $recordedAt,
                'user_id' => (int) $this->user,
            ], title: __('Add Metric').' ('.MetricEnum::OneRepMax->label().')');
        }
    }

    public function openPlanHeartRateEdit(): void
    {
        if ($this->user === '') {
            return;
        }

        $data = $this->planHeartRateData;

        if ($data['maxHR'] !== null) {
            $this->dispatch('open-calendar-metric-form', data: [
                'metric' => MetricEnum::HeartRate->value,
                'recorded_at' => now()->format('Y-m-d'),
                'user_id' => (int) $this->user,
            ], title: __('Edit Metric').' ('.MetricEnum::HeartRate->label().')');
        } else {
            $block = $this->planBlock !== 'ungrouped' ? TrainingProgramBlock::find((int) $this->planBlock) : null;
            $recordedAt = $block?->start?->format('Y-m-d') ?? now()->format('Y-m-d');

            $this->dispatch('open-calendar-metric-form', data: [
                'metric' => MetricEnum::HeartRate->value,
                'recorded_at' => $recordedAt,
                'user_id' => (int) $this->user,
            ], title: __('Add Metric').' ('.MetricEnum::HeartRate->label().')');
        }
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
}
