<?php

namespace App\Training;

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\Metrics\HeartRateMetric;
use App\Data\Athlete\Metric\Metrics\OneRepMaxMetric;
use App\Data\Exercise\ExerciseSetting;
use App\Data\Exercise\Preview\CellInputMeta;
use App\Data\Exercise\Settings\RepsSetting;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Training\Compiler\AuthoringExerciseData;
use App\Data\Training\Compiler\AuthoringProgramData;
use App\Data\Training\Compiler\PlanningContextData;
use App\Data\Training\Compiled\CompiledTrainingExercise;
use App\Data\Training\Compiled\CompiledTrainingSession;
use App\Data\Training\Compiled\CompiledTrainingSet;
use App\Data\Training\Compiled\CompiledTrainingSetValue;
use App\Data\Training\Planned\ResolvedPlannedExercise;
use App\Data\Training\Planned\ResolvedPlannedSet;
use App\Data\Training\Planned\ResolvedPlannedValue;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Athlete\MetricSubmission;
use App\Models\Exercise\Exercise;
use App\Models\Training\TrainingProgramSlot;
use App\Support\Training\ProgramExerciseOrder;
use App\Training\Planning\PlanCompiler;
use Carbon\Carbon;

class TrainingSessionCompiler
{
    /**
     * @var array<string, array<int, array{slotIndex: int, weekIndex: int, sessionIndex: int, sessionsPerWeek: int, weekSessionCounts: array<int, int>}>>
     */
    private array $sessionContextCache = [];

    /**
     * @var array<string, array{cutoffDate: string, targetGoal: int|float}>
     */
    private array $metricContextCache = [];

    /**
     * @var array<string, OneRepMaxMetric|HeartRateMetric|null>
     */
    private array $latestMetricCache = [];

    public function __construct(
        private readonly CalendarBlockService $calendarBlockService,
        private readonly PlanCompiler $planCompiler,
        private readonly ProgramExerciseOrder $programExerciseOrder,
    ) {}

    public function compile(TrainingProgramSlot $slot): CompiledTrainingSession
    {
        $slot->loadMissing('trainingProgram.program.exercises');

        $program = $slot->trainingProgram->program;
        $programConfig = $program->config;
        $scheduledDate = ($slot->scheduled_date ?? $slot->datetime)->format('Y-m-d');
        $metricContext = $this->resolveMetricContext($slot, $scheduledDate);
        $sessionContext = $this->resolveSessionContext($slot, $scheduledDate);
        $oneRepMaxMetric = $this->latestMetric($slot->user_id, MetricEnum::OneRepMax, $metricContext['cutoffDate']);
        $heartRateMetric = $this->latestMetric($slot->user_id, MetricEnum::HeartRate, $scheduledDate);
        $weightProgression = $this->resolveWeightProgressionData($oneRepMaxMetric, $metricContext['targetGoal']);
        $authoringProgram = new AuthoringProgramData(
            exercises: $this->programExerciseOrder
            ->sortProgramExercises($program->exercises)
            ->values()
            ->map(function (Exercise $exercise, int $index) use ($programConfig, $slot) {
                $programExerciseId = (int) $exercise->pivot->id;
                $resolvedOverrides = $programConfig->resolveExercise($exercise->config, $programExerciseId, $slot->user_id);

                return new AuthoringExerciseData(
                    exerciseId: $exercise->id,
                    sort: $index,
                    group: $exercise->pivot->group,
                    type: $exercise->pivot->type ?? 'main',
                    effectiveConfig: $resolvedOverrides->effectiveConfig,
                    overrideLayer: $resolvedOverrides->overrideLayer,
                    baseConfig: $exercise->config->toArray(),
                    defaultOverrides: $resolvedOverrides->defaultOverrides,
                    userOverrides: $resolvedOverrides->userOverrides,
                    disabled: (bool) $resolvedOverrides->disabled,
                );
            })
            ->values()
            ->all(),
        );
        $planningContext = new PlanningContextData(
            scheduledDate: $scheduledDate,
            weekIndex: (int) $sessionContext['weekIndex'],
            sessionIndex: (int) $sessionContext['sessionIndex'],
            sessionsPerWeek: (int) ($sessionContext['sessionsPerWeek'] ?? 1),
            weekSessionCounts: $sessionContext['weekSessionCounts'] ?? [1],
            weightProgression: $weightProgression,
            maxHR: $heartRateMetric?->heartRate,
            iatPercent: $heartRateMetric?->anaerobicThreshold,
        );
        $plannedSession = $this->planCompiler->compile($authoringProgram, $planningContext);
        $compiledExercises = array_map(
            fn (ResolvedPlannedExercise $exercise): CompiledTrainingExercise => $this->compileExercise($exercise),
            $plannedSession->exercises,
        );

        return new CompiledTrainingSession(
            slotId: $slot->id,
            scheduledDate: $scheduledDate,
            compiledVersion: sha1(json_encode($this->serializeExercises($compiledExercises), JSON_THROW_ON_ERROR)),
            exercises: $compiledExercises,
        );
    }

    private function resolveSessionContext(TrainingProgramSlot $slot, string $scheduledDate): array
    {
        $cacheKey = $this->sessionContextCacheKey($slot, $scheduledDate);

        if (! isset($this->sessionContextCache[$cacheKey])) {
            $slots = $this->sessionContextSlots($slot, $scheduledDate);

            $contexts = [];
            $slotIndexes = $slots
                ->pluck('id')
                ->flip()
                ->map(fn ($index) => (int) $index)
                ->all();

            $weeks = $slots
                ->groupBy(fn (TrainingProgramSlot $scheduledSlot) => $scheduledSlot->datetime->isoWeekYear().'-'.$scheduledSlot->datetime->isoWeek())
                ->values();
            $weekSessionCounts = [];

            foreach ($weeks as $weekIndex => $weekSlots) {
                $sessionCount = max(1, $weekSlots->count());
                $weekSessionCounts[$weekIndex] = $sessionCount;
            }

            foreach ($weeks as $weekIndex => $weekSlots) {
                $sessionCount = $weekSessionCounts[$weekIndex] ?? max(1, $weekSlots->count());

                foreach ($weekSlots->values() as $sessionIndex => $scheduledSlot) {
                    $contexts[(int) $scheduledSlot->id] = [
                        'slotIndex' => $slotIndexes[(int) $scheduledSlot->id] ?? 0,
                        'weekIndex' => (int) $weekIndex,
                        'sessionIndex' => (int) $sessionIndex,
                        'sessionsPerWeek' => $sessionCount,
                        'weekSessionCounts' => $weekSessionCounts,
                    ];
                }
            }

            $this->sessionContextCache[$cacheKey] = $contexts;
        }

        return $this->sessionContextCache[$cacheKey][$slot->id] ?? [
            'slotIndex' => 0,
            'weekIndex' => 0,
            'sessionIndex' => 0,
            'sessionsPerWeek' => 1,
            'weekSessionCounts' => [1],
        ];
    }

    private function sessionContextCacheKey(TrainingProgramSlot $slot, string $scheduledDate): string
    {
        $block = $this->resolveOverlappingCategoryBlock($slot, $scheduledDate);

        if ($block instanceof TrainingProgramBlock) {
            return $slot->training_program_id.':'.$slot->user_id.':block:'.$block->id;
        }

        return $slot->training_program_id.':'.$slot->user_id.':all';
    }

    /**
     * @return \Illuminate\Support\Collection<int, TrainingProgramSlot>
     */
    private function sessionContextSlots(TrainingProgramSlot $slot, string $scheduledDate): \Illuminate\Support\Collection
    {
        $query = TrainingProgramSlot::query()
            ->where('training_program_id', $slot->training_program_id)
            ->where('user_id', $slot->user_id)
            ->whereNull('cancelled_at');

        $block = $this->resolveOverlappingCategoryBlock($slot, $scheduledDate);

        if ($block instanceof TrainingProgramBlock) {
            $query->whereBetween('datetime', [
                $block->start->copy()->startOfDay(),
                ($block->end ?? $block->start)->copy()->endOfDay(),
            ]);
        }

        return $query
            ->orderBy('datetime')
            ->orderBy('id')
            ->get(['id', 'datetime'])
            ->values();
    }

    /**
     * @return array{cutoffDate: string, targetGoal: int|float}
     */
    private function resolveMetricContext(TrainingProgramSlot $slot, string $scheduledDate): array
    {
        $cacheKey = $slot->training_program_id.':'.$slot->user_id.':'.$scheduledDate;

        if (isset($this->metricContextCache[$cacheKey])) {
            return $this->metricContextCache[$cacheKey];
        }

        $program = $slot->trainingProgram->program;
        $fallback = [
            'cutoffDate' => $scheduledDate,
            'targetGoal' => $program->config->defaultTargetGoal(),
        ];

        $categoryId = $program->exercise_category_id;
        if (! $categoryId) {
            return $this->metricContextCache[$cacheKey] = $fallback;
        }

        $block = $this->resolveOverlappingCategoryBlock($slot, $scheduledDate);

        if (! $block instanceof TrainingProgramBlock) {
            return $this->metricContextCache[$cacheKey] = $fallback;
        }

        return $this->metricContextCache[$cacheKey] = [
            'cutoffDate' => $block->start?->format('Y-m-d') ?? $scheduledDate,
            'targetGoal' => $block->config?->goal ?? $fallback['targetGoal'],
        ];
    }

    private function resolveOverlappingCategoryBlock(TrainingProgramSlot $slot, string $scheduledDate): ?TrainingProgramBlock
    {
        $program = $slot->trainingProgram->program;
        $categoryId = $program->exercise_category_id;

        if (! $categoryId) {
            return null;
        }

        return $this->calendarBlockService->findOverlappingBlock(
            groupId: (int) $slot->trainingProgram->group_id,
            userId: (int) $slot->user_id,
            categoryId: (int) $categoryId,
            date: Carbon::parse($scheduledDate),
        );
    }

    private function latestMetric(int $userId, MetricEnum $metric, string $scheduledDate): OneRepMaxMetric|HeartRateMetric|null
    {
        $cacheKey = $userId.':'.$metric->value.':'.$scheduledDate;

        if (array_key_exists($cacheKey, $this->latestMetricCache)) {
            return $this->latestMetricCache[$cacheKey];
        }

        $submission = MetricSubmission::query()
            ->forAthlete($userId)
            ->forMetric($metric)
            ->whereDate('recorded_at', '<=', $scheduledDate)
            ->manual()
            ->with('values')
            ->latest('recorded_at')
            ->latest('id')
            ->first();

        if (! $submission) {
            return $this->latestMetricCache[$cacheKey] = null;
        }

        $values = $submission->values->pluck('value', 'field')->all();

        return $this->latestMetricCache[$cacheKey] = match ($metric) {
            MetricEnum::OneRepMax => OneRepMaxMetric::from($values),
            MetricEnum::HeartRate => HeartRateMetric::from($values),
        };
    }

    private function resolveWeightProgressionData(?OneRepMaxMetric $metric, int|float $targetGoal): ?WeightProgressionSetting
    {
        if (! $metric) {
            return null;
        }

        return new WeightProgressionSetting(
            measuredReps: $metric->measuredReps,
            measuredWeight: $metric->measuredWeight,
            targetGoal: (int) $targetGoal,
        );
    }

    private function compileExercise(ResolvedPlannedExercise $exercise): CompiledTrainingExercise
    {
        return new CompiledTrainingExercise(
            exerciseId: (int) $exercise->exerciseId,
            sort: $exercise->sort,
            group: $exercise->group,
            type: $exercise->type,
            sets: array_map(
                fn (ResolvedPlannedSet $set): CompiledTrainingSet => $this->compileSet($set),
                $exercise->sets,
            ),
        );
    }

    private function compileSet(ResolvedPlannedSet $set): CompiledTrainingSet
    {
        return new CompiledTrainingSet(
            setNumber: $set->setNumber,
            values: array_map(
                fn (ResolvedPlannedValue $value): CompiledTrainingSetValue => $this->compileValue($value),
                $set->values,
            ),
        );
    }

    private function compileValue(ResolvedPlannedValue $value): CompiledTrainingSetValue
    {
        $normalizedValue = $this->normalizeCompiledValue($value->settingKey, $value->value, ['unit' => $value->unit]);

        return new CompiledTrainingSetValue(
            settingKey: $value->settingKey,
            plannedValueType: $this->resolveValueType($value->settingKey, $normalizedValue),
            plannedValue: $normalizedValue,
            plannedCanonicalValue: $this->resolveCanonicalValue($value->settingKey, $normalizedValue),
            unit: $value->unit,
        );
    }

    private function normalizeCompiledValue(string $setting, mixed $value, array $config): mixed
    {
        if ($setting !== 'duration') {
            return $value;
        }

        $unit = $config['unit'] ?? 'seconds';

        if ($unit === 'mm:ss' && is_string($value) && str_contains($value, ':')) {
            [$minutes, $seconds] = array_pad(explode(':', $value, 2), 2, '0');

            return ((int) $minutes * 60) + (int) $seconds;
        }

        return $value;
    }

    private function resolveValueType(string $setting, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return 'json';
        }

        $enum = ExerciseSetting::tryFrom($setting);
        $settingClass = $enum?->settingClass();
        $inputMeta = $settingClass ? $settingClass::inputMeta() : new CellInputMeta;

        if (($inputMeta->inputType ?? 'text') === 'text') {
            if ($setting === 'duration' && is_numeric($value)) {
                return 'int';
            }

            return 'string';
        }

        if (is_float($value)) {
            return 'decimal';
        }

        if (is_string($value) && str_contains($value, '.')) {
            return is_numeric($value) ? 'decimal' : 'string';
        }

        if (is_numeric($value)) {
            return 'int';
        }

        return 'string';
    }

    private function resolveCanonicalValue(string $setting, mixed $value): ?array
    {
        return match ($setting) {
            'reps' => $this->resolveRepsCanonicalValue($value),
            'heartRate' => $this->resolveBoundedRangeCanonicalValue($value, 'heart_rate'),
            'heartRateZone' => $this->resolveBoundedRangeCanonicalValue($value, 'heart_rate_zone'),
            default => null,
        };
    }

    private function resolveRepsCanonicalValue(mixed $value): ?array
    {
        return RepsSetting::athleteCanonicalValue($value);
    }

    private function resolveBoundedRangeCanonicalValue(mixed $value, string $kind): ?array
    {
        if (is_int($value) || (is_string($value) && preg_match('/^\d+$/', $value))) {
            $numeric = (int) $value;

            return [
                'kind' => $kind,
                'format' => 'scalar',
                'display' => (string) $value,
                'value' => $numeric,
                'min' => $numeric,
                'max' => $numeric,
            ];
        }

        if (! is_string($value) || ! preg_match('/^(?<min>\d+)-(?<max>\d+)$/', $value, $matches)) {
            return null;
        }

        return [
            'kind' => $kind,
            'format' => 'range',
            'display' => $value,
            'min' => (int) $matches['min'],
            'max' => (int) $matches['max'],
        ];
    }

    /**
     * @param  CompiledTrainingExercise[]  $exercises
     * @return array<int, array<string, mixed>>
     */
    private function serializeExercises(array $exercises): array
    {
        return array_map(function (CompiledTrainingExercise $exercise): array {
            return [
                'exerciseId' => $exercise->exerciseId,
                'sort' => $exercise->sort,
                'group' => $exercise->group,
                'type' => $exercise->type,
                'sets' => array_map(function (CompiledTrainingSet $set): array {
                    return [
                        'setNumber' => $set->setNumber,
                        'values' => array_map(fn (CompiledTrainingSetValue $value) => [
                        'settingKey' => $value->settingKey,
                        'plannedValueType' => $value->plannedValueType,
                        'plannedValue' => $value->plannedValue,
                        'plannedCanonicalValue' => $value->plannedCanonicalValue,
                        'unit' => $value->unit,
                    ], $set->values),
                ];
            }, $exercise->sets),
            ];
        }, $exercises);
    }
}
