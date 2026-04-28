<?php

namespace App\Training;

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\Metrics\HeartRateMetric;
use App\Data\Athlete\Metric\Metrics\OneRepMaxMetric;
use App\Data\Exercise\ExerciseSetting;
use App\Data\Exercise\Preview\CellInputMeta;
use App\Data\Exercise\Preview\GridOverrides;
use App\Data\Exercise\Preview\GridState;
use App\Data\Exercise\Preview\StrategyOrchestrator;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Training\Compiled\CompiledTrainingExercise;
use App\Data\Training\Compiled\CompiledTrainingSession;
use App\Data\Training\Compiled\CompiledTrainingSet;
use App\Data\Training\Compiled\CompiledTrainingSetValue;
use App\Data\Training\Config\EffectiveExerciseConfig;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Athlete\MetricSubmission;
use App\Models\Exercise\Exercise;
use App\Models\Training\TrainingProgramSlot;
use Carbon\Carbon;

class TrainingSessionCompiler
{
    private const SECTION_ORDER = [
        'warm_up',
        'main',
        'warm_down',
    ];

    private const SETTING_PRIORITY = [
        'reps',
        'weight',
        'distance',
        'duration',
        'pace',
        'watts',
        'heartRate',
        'heartRateZone',
        'tempo',
        'rest',
        'note',
    ];

    public function __construct(
        private readonly CalendarBlockService $calendarBlockService,
    ) {}

    public function compile(TrainingProgramSlot $slot): CompiledTrainingSession
    {
        $slot->loadMissing('trainingProgram.program.exercises');

        $program = $slot->trainingProgram->program;
        $programConfig = $program->config;
        $scheduledDate = ($slot->scheduled_date ?? $slot->datetime)->format('Y-m-d');
        $sessionContext = $this->resolveSessionContext($slot);
        $metricContext = $this->resolveMetricContext($slot, $scheduledDate);
        $oneRepMaxMetric = $this->latestMetric($slot->user_id, MetricEnum::OneRepMax, $metricContext['cutoffDate']);
        $heartRateMetric = $this->latestMetric($slot->user_id, MetricEnum::HeartRate, $scheduledDate);
        $weightProgression = $this->resolveWeightProgressionData($oneRepMaxMetric, $metricContext['targetGoal']);

        $compiledExercises = $program->exercises
            ->sortBy(function (Exercise $exercise): string {
                $type = $exercise->pivot->type ?? 'main';
                $sectionRank = array_search($type, self::SECTION_ORDER, true);

                return sprintf(
                    '%02d-%08d-%08d',
                    $sectionRank === false ? count(self::SECTION_ORDER) : $sectionRank,
                    (int) ($exercise->pivot->sort ?? 0),
                    (int) ($exercise->pivot->id ?? 0),
                );
            })
            ->values()
            ->map(function (Exercise $exercise, int $index) use ($programConfig, $sessionContext, $weightProgression, $heartRateMetric, $slot, $scheduledDate) {
                $programExerciseId = (int) $exercise->pivot->id;
                $planOverrides = $programConfig->defaultExerciseOverrides($programExerciseId);
                $userOverrides = $programConfig->userExerciseOverrides($slot->user_id, $programExerciseId);

                if (! $this->exerciseAppliesOnDate($planOverrides->startsAtDate, $userOverrides->startsAtDate, $scheduledDate)) {
                    return null;
                }

                if (EffectiveExerciseConfig::resolveDisabled($planOverrides, $userOverrides)) {
                    return null;
                }

                $effectiveConfig = EffectiveExerciseConfig::resolve($exercise->config, $planOverrides, $userOverrides);
                $overrideLayer = EffectiveExerciseConfig::resolveForLayer($exercise->config, $planOverrides, $userOverrides);

                $weeks = max(
                    (int) data_get($effectiveConfig, 'preview.weeks', 1),
                    $sessionContext['weekIndex'] + 1
                );

                $state = (new StrategyOrchestrator(
                    data: $effectiveConfig,
                    measuredData: $weightProgression,
                    weeks: $weeks,
                    overrides: GridOverrides::fromArrays(
                        $overrideLayer['cells'] ?? [],
                        $overrideLayer['weeks'] ?? [],
                    ),
                    maxHR: $heartRateMetric?->heartRate,
                    iatPercent: $heartRateMetric?->anaerobicThreshold,
                ))->execute();

                $weekIndex = $sessionContext['weekIndex'];
                $setCount = (int) ($state->getSetsPerWeek()[$weekIndex] ?? data_get($effectiveConfig, 'sets.default', 0));

                $compiledSets = [];
                for ($setIndex = 0; $setIndex < $setCount; $setIndex++) {
                    $values = [];

                    foreach ($this->orderedSettings($effectiveConfig['settings'] ?? []) as $setting) {
                        $config = $effectiveConfig[$setting] ?? [];
                        $applyPer = $config['applyPer'] ?? 'session';
                        $value = $applyPer === 'week'
                            ? $this->resolveWeekValue($state, $config, $setting, $weekIndex)
                            : $this->resolveSessionValue($state, $config, $setting, $weekIndex, $setIndex, $sessionContext['sessionIndex']);

                        if ($this->isBlankValue($value)) {
                            continue;
                        }

                        $value = $this->normalizeCompiledValue($setting, $value, $config);

                        $values[] = new CompiledTrainingSetValue(
                            settingKey: $setting,
                            plannedValueType: $this->resolveValueType($setting, $value),
                            plannedValue: $value,
                            unit: $this->resolveUnit($setting, $config),
                        );
                    }

                    $compiledSets[] = new CompiledTrainingSet(
                        setNumber: $setIndex + 1,
                        values: $values,
                    );
                }

                return new CompiledTrainingExercise(
                    exerciseId: $exercise->id,
                    sort: $index,
                    group: $exercise->pivot->group,
                    type: $exercise->pivot->type ?? 'main',
                    sets: $compiledSets,
                );
            })
            ->filter()
            ->values()
            ->all();

        return new CompiledTrainingSession(
            slotId: $slot->id,
            scheduledDate: $scheduledDate,
            compiledVersion: sha1(json_encode($this->serializeExercises($compiledExercises), JSON_THROW_ON_ERROR)),
            exercises: $compiledExercises,
        );
    }

    private function resolveSessionContext(TrainingProgramSlot $slot): array
    {
        $slots = TrainingProgramSlot::query()
            ->where('training_program_id', $slot->training_program_id)
            ->where('user_id', $slot->user_id)
            ->whereNull('cancelled_at')
            ->orderBy('datetime')
            ->orderBy('id')
            ->get(['id', 'datetime']);

        $slotIndex = $slots->search(fn (TrainingProgramSlot $scheduledSlot) => $scheduledSlot->id === $slot->id);
        $slotIndex = $slotIndex === false ? 0 : (int) $slotIndex;

        $weeks = $slots
            ->values()
            ->groupBy(fn (TrainingProgramSlot $scheduledSlot) => $scheduledSlot->datetime->isoWeekYear().'-'.$scheduledSlot->datetime->isoWeek())
            ->values();

        $weekIndex = 0;
        $sessionIndex = 0;
        $sessionsPerWeek = 1;

        foreach ($weeks as $index => $weekSlots) {
            $position = $weekSlots->search(fn (TrainingProgramSlot $scheduledSlot) => $scheduledSlot->id === $slot->id);

            if ($position === false) {
                continue;
            }

            $weekIndex = (int) $index;
            $sessionIndex = (int) $position;
            $sessionsPerWeek = max(1, $weekSlots->count());

            break;
        }

        return [
            'slotIndex' => $slotIndex,
            'weekIndex' => $weekIndex,
            'sessionIndex' => $sessionIndex,
            'sessionsPerWeek' => $sessionsPerWeek,
        ];
    }

    /**
     * @return array{cutoffDate: string, targetGoal: int|float}
     */
    private function resolveMetricContext(TrainingProgramSlot $slot, string $scheduledDate): array
    {
        $program = $slot->trainingProgram->program;
        $fallback = [
            'cutoffDate' => $scheduledDate,
            'targetGoal' => $program->config->defaultTargetGoal(),
        ];

        $categoryId = $program->exercise_category_id;
        if (! $categoryId) {
            return $fallback;
        }

        $block = $this->calendarBlockService->findOverlappingBlock(
            groupId: (int) $slot->trainingProgram->group_id,
            userId: (int) $slot->user_id,
            categoryId: (int) $categoryId,
            date: Carbon::parse($scheduledDate),
        );

        if (! $block instanceof TrainingProgramBlock) {
            return $fallback;
        }

        return [
            'cutoffDate' => $block->start?->format('Y-m-d') ?? $scheduledDate,
            'targetGoal' => $block->config?->goal ?? $fallback['targetGoal'],
        ];
    }

    private function latestMetric(int $userId, MetricEnum $metric, string $scheduledDate): OneRepMaxMetric|HeartRateMetric|null
    {
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
            return null;
        }

        $values = $submission->values->pluck('value', 'field')->all();

        return match ($metric) {
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

    private function exerciseAppliesOnDate(?string $planStartsAtDate, ?string $userStartsAtDate, string $scheduledDate): bool
    {
        $startsAtDate = $userStartsAtDate ?? $planStartsAtDate;

        if ($startsAtDate === null || $startsAtDate === '') {
            return true;
        }

        return $scheduledDate >= $startsAtDate;
    }

    private function resolveSessionValue(GridState $state, array $config, string $setting, int $weekIndex, int $setIndex, int $sessionIndex): mixed
    {
        $resolved = $state->getResolvedCellValue($setting, $weekIndex, $setIndex, $sessionIndex);

        return $resolved ?? $this->defaultValue($config);
    }

    private function resolveWeekValue(GridState $state, array $config, string $setting, int $weekIndex): mixed
    {
        return $state->getResolvedWeekValue($setting, $weekIndex, $this->defaultValue($config));
    }

    private function defaultValue(array $config): mixed
    {
        $default = $config['default'] ?? null;

        return $this->isBlankValue($default) ? null : $default;
    }

    private function isBlankValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === '-' || $value === '—';
    }

    /**
     * @param  string[]  $settings
     * @return string[]
     */
    private function orderedSettings(array $settings): array
    {
        usort($settings, function (string $a, string $b): int {
            $priorityA = array_search($a, self::SETTING_PRIORITY, true);
            $priorityB = array_search($b, self::SETTING_PRIORITY, true);

            return ($priorityA === false ? PHP_INT_MAX : $priorityA)
                <=> ($priorityB === false ? PHP_INT_MAX : $priorityB);
        });

        return array_values($settings);
    }

    private function resolveUnit(string $setting, array $config): ?string
    {
        $enum = ExerciseSetting::tryFrom($setting);
        $settingClass = $enum?->settingClass();

        return $settingClass ? $settingClass::resolveUnitLabel($config) : null;
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

    private function resolveValueType(string $setting, mixed $value): string
    {
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
                            'unit' => $value->unit,
                        ], $set->values),
                    ];
                }, $exercise->sets),
            ];
        }, $exercises);
    }
}
