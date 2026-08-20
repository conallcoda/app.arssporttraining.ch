<?php

namespace App\Training\Planning;

use App\Data\Training\Compiler\AuthoringExerciseData;
use App\Data\Training\Compiler\AuthoringProgramData;
use App\Data\Training\Compiler\PlanningContextData;
use App\Data\Training\Planned\ResolvedPlannedSession;
use App\Support\Profiling\PlanGridProfiler;
use App\Support\Training\ExerciseMetricAvailability;

class PlanCompiler
{
    public function __construct(
        private readonly ResolvedPlannedSessionBuilder $plannedSessionBuilder,
        private readonly ExerciseMetricAvailability $exerciseMetricAvailability,
    ) {}

    public function compile(AuthoringProgramData $program, PlanningContextData $context): ResolvedPlannedSession
    {
        $span = PlanGridProfiler::start('PlanCompiler.compile', [
            'week' => $context->weekIndex,
            'session' => $context->sessionIndex,
            'exercise_count' => count($program->exercises),
            'scheduled_date' => $context->scheduledDate,
        ]);

        try {
            $exerciseConfigs = collect($program->exercises)
                ->map(function (AuthoringExerciseData $exercise) use ($context): ?array {
                    if ($exercise->disabled) {
                        return null;
                    }

                    if ($this->exerciseMetricAvailability->missingRequiredMetrics(
                        effectiveConfig: $exercise->effectiveConfig,
                        weightProgression: $context->weightProgression,
                        maxHR: $context->maxHR,
                        iatPercent: $context->iatPercent,
                    )) {
                        return null;
                    }

                    return [
                        'exerciseId' => $exercise->exerciseId,
                        'programExerciseId' => $exercise->programExerciseId,
                        'sort' => $exercise->sort,
                        'group' => $exercise->group,
                        'type' => $exercise->type,
                        'effectiveConfig' => $exercise->effectiveConfig,
                        'overrideLayer' => $exercise->overrideLayer,
                        'baseConfig' => $exercise->baseConfig,
                        'defaultOverrides' => $exercise->defaultOverrides,
                        'userOverrides' => $exercise->userOverrides,
                        'effectiveVideoUrl' => $exercise->effectiveVideoUrl,
                        'effectiveInstructions' => $exercise->effectiveInstructions,
                        'measuredData' => $context->weightProgression,
                        'maxHR' => $context->maxHR,
                        'iatPercent' => $context->iatPercent,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            $weeks = max(
                $context->resolvedWeekCount(),
                collect($program->exercises)
                    ->map(fn (AuthoringExerciseData $exercise): int => (int) data_get($exercise->effectiveConfig, 'preview.weeks', 1))
                    ->max() ?? 1,
            );

            return $this->plannedSessionBuilder->build(
                weekIndex: $context->weekIndex,
                sessionIndex: $context->sessionIndex,
                scheduledDate: $context->scheduledDate,
                exerciseConfigs: $exerciseConfigs,
                weeks: $weeks,
                sessionCounts: $context->resolvedSessionCounts($weeks),
                slotIndex: $context->slotIndex,
                useSlotIndexForGroupedSessions: $context->useSlotIndexForGroupedSessions,
            );
        } finally {
            PlanGridProfiler::end($span, [
                'resolved_exercise_count' => isset($exerciseConfigs) ? count($exerciseConfigs) : null,
                'weeks' => $weeks ?? null,
            ]);
        }
    }
}
