<?php

namespace App\Training;

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\Metrics\HeartRateMetric;
use App\Data\Athlete\Metric\Metrics\OneRepMaxMetric;
use App\Data\Exercise\ExerciseSetting;
use App\Data\Exercise\Preview\GridOverrides;
use App\Data\Exercise\Preview\GridState;
use App\Data\Exercise\Preview\StrategyOrchestrator;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Training\Compiled\CompiledTrainingExercise;
use App\Data\Training\Compiled\CompiledTrainingSession;
use App\Data\Training\Compiled\CompiledTrainingSet;
use App\Data\Training\Compiled\CompiledTrainingSetValue;
use App\Data\Training\Config\EffectiveExerciseConfig;
use App\Models\Athlete\MetricSubmission;
use App\Models\Exercise\Exercise;
use App\Models\Training\TrainingProgramSlot;

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

    public function compile(TrainingProgramSlot $slot): CompiledTrainingSession
    {
        $slot->loadMissing('trainingProgram.program.exercises');

        $program = $slot->trainingProgram->program;
        $programConfig = $program->config;
        $scheduledDate = ($slot->scheduled_date ?? $slot->datetime)->format('Y-m-d');
        $sessionContext = $this->resolveSessionContext($slot);
        $oneRepMaxMetric = $this->latestMetric($slot->user_id, MetricEnum::OneRepMax, $scheduledDate);
        $heartRateMetric = $this->latestMetric($slot->user_id, MetricEnum::HeartRate, $scheduledDate);
        $weightProgression = $this->resolveWeightProgressionData($oneRepMaxMetric, $programConfig->defaultTargetGoal());

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
                            : $this->resolveSessionValue($state, $config, $setting, $weekIndex, $setIndex);

                        if ($this->isBlankValue($value)) {
                            continue;
                        }

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
        $slotIds = TrainingProgramSlot::query()
            ->where('training_program_id', $slot->training_program_id)
            ->where('user_id', $slot->user_id)
            ->orderBy('datetime')
            ->orderBy('id')
            ->pluck('id')
            ->values();

        $slotIndex = $slotIds->search($slot->id);
        $slotIndex = $slotIndex === false ? 0 : (int) $slotIndex;

        $sessionsPerWeek = 1;

        return [
            'slotIndex' => $slotIndex,
            'weekIndex' => intdiv($slotIndex, $sessionsPerWeek),
            'sessionIndex' => $slotIndex % $sessionsPerWeek,
            'sessionsPerWeek' => $sessionsPerWeek,
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

    private function resolveSessionValue(GridState $state, array $config, string $setting, int $weekIndex, int $setIndex): mixed
    {
        $resolved = $state->getResolvedCellValue($setting, $weekIndex, $setIndex);

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

    private function resolveValueType(string $setting, mixed $value): string
    {
        return match ($setting) {
            'tempo', 'pace', 'note' => 'string',
            'reps', 'rest', 'watts', 'heartRate', 'heartRateZone' => 'int',
            'weight', 'distance' => 'decimal',
            'duration' => is_numeric($value) ? 'int' : 'string',
            default => is_array($value) ? 'json' : (is_string($value) ? 'string' : (is_float($value) ? 'decimal' : 'int')),
        };
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
