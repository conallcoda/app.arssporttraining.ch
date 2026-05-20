<?php

namespace App\Support\Training;

use App\Data\Athlete\ProgramDetailsExerciseData;
use App\Data\Athlete\ScheduledProgramData;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Training\Compiler\AuthoringExerciseData;
use App\Data\Training\Compiler\AuthoringProgramData;
use App\Data\Training\Compiler\PlanningContextData;
use App\Data\Training\Planned\ResolvedPlannedExercise;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Support\Athlete\PlannedProgramDetailsExerciseViewBuilder;
use App\Support\AthleteDashboardDate;
use App\Training\ExerciseGroupLabeler;
use App\Training\Planning\PlanCompiler;
use Carbon\CarbonImmutable;

class ProgramPreviewBuilder
{
    public function __construct(
        private readonly PlanCompiler $planCompiler,
        private readonly PlannedProgramDetailsExerciseViewBuilder $exerciseViewBuilder,
        private readonly ProgramExerciseOrder $programExerciseOrder,
    ) {}

    /**
     * @return array{
     *     days: array<int, array{
     *         date: string,
     *         isFuture: bool,
     *         formattedDate: string,
     *         sessionCount: int,
     *         programs: \Illuminate\Support\Collection<int, ScheduledProgramData>
     *     }>,
     *     sessions: array<string, array{
     *         key: string,
     *         date: string,
     *         formattedDate: string,
     *         timeLabel: string,
     *         isFutureSession: bool,
     *         categoryLabel: ?string,
     *         categoryColor: ?string,
     *         progressSegments: array<int, array{submitted: bool, color: array{light: string, dark: string}}>,
     *         sectionTabs: array<int, array{key: string, label: string, count: int}>,
     *         exercisesBySection: array<string, array<int, ProgramDetailsExerciseData>>
     *     }>
     * }
     */
    public function build(
        ExerciseProgram $exerciseProgram,
        int $weeks,
        int $sessionsPerWeek,
        array $weekLabels = [],
        array $weekSessions = [],
        array $weekSessionDates = [],
        ?int $userId = null,
        ?int $planMeasuredReps = null,
        ?float $planMeasuredWeight = null,
        int|float|null $planTargetGoal = null,
        ?int $planMaxHR = null,
        ?int $planIatPercent = null,
    ): array {
        $exerciseProgram->loadMissing([
            'exerciseCategory',
            'exercises' => fn ($query) => $query->orderByPivot('type')->orderByPivot('sort')->orderByPivot('id'),
            'exercises.equipment',
            'exercises.modifiers',
            'exercises.media',
        ]);

        $programConfig = $exerciseProgram->config;
        $resolvedWeeks = max($weeks, 1);
        $resolvedSessionCounts = WeekSessionCountResolver::resolveForWeeks(
            weeks: $resolvedWeeks,
            fallbackSessionsPerWeek: $sessionsPerWeek,
            weekSessions: $weekSessions,
            weekSessionDates: $weekSessionDates,
        );

        $weightProgression = $planMeasuredReps !== null && $planMeasuredWeight !== null
            ? new WeightProgressionSetting(
                measuredReps: $planMeasuredReps,
                measuredWeight: $planMeasuredWeight,
                targetGoal: $planTargetGoal ?? $exerciseProgram->config->defaultTargetGoal(),
            )
            : null;

        $sortedExercises = $this->programExerciseOrder
            ->sortProgramExercises($exerciseProgram->exercises)
            ->values();

        $groupLabels = ExerciseGroupLabeler::label(
            $sortedExercises,
            fn (Exercise $exercise): ?string => $exercise->pivot->group,
            fn (Exercise $exercise): int => (int) $exercise->pivot->id,
        );
        $sourceExercisesBySignature = $sortedExercises
            ->keyBy(fn (Exercise $exercise): string => $this->exerciseSignature(
                exerciseId: $exercise->id,
                sort: (int) ($exercise->pivot->sort ?? 0),
                group: $exercise->pivot->group,
                type: (string) ($exercise->pivot->type ?? 'main'),
            ));
        $authoringProgram = new AuthoringProgramData(
            exercises: $sortedExercises
                ->map(function (Exercise $exercise, int $index) use ($programConfig, $userId): AuthoringExerciseData {
                    $programExerciseId = (int) $exercise->pivot->id;
                    $resolvedOverrides = $programConfig->resolveExercise($exercise->config, $programExerciseId, $userId);

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
                ->all(),
        );

        $days = [];
        $sessions = [];
        $sessionNumber = 1;

        for ($weekIndex = 0; $weekIndex < $resolvedWeeks; $weekIndex++) {
            $sessionCount = $resolvedSessionCounts[$weekIndex] ?? 1;

            for ($sessionIndex = 0; $sessionIndex < $sessionCount; $sessionIndex++) {
                $date = $weekSessionDates[$weekIndex][$sessionIndex] ?? null;
                $formattedDate = $date
                    ? CarbonImmutable::parse($date)->locale(app()->getLocale())->translatedFormat('l, d.m.Y')
                    : strip_tags((string) ($weekLabels[$weekIndex] ?? 'Week '.($weekIndex + 1)));
                $sessionKey = $weekIndex.'-'.$sessionIndex;
                $timeLabel = 'Session '.$sessionNumber;
                $isFutureSession = $date
                    ? AthleteDashboardDate::isFutureDate($date)
                    : true;
                $plannedSession = $this->planCompiler->compile(
                    $authoringProgram,
                    new PlanningContextData(
                        scheduledDate: $date ?? 'preview-week-'.$weekIndex.'-session-'.$sessionIndex,
                        weekIndex: $weekIndex,
                        sessionIndex: $sessionIndex,
                        sessionsPerWeek: max(1, $resolvedSessionCounts[$weekIndex] ?? $sessionsPerWeek),
                        weekSessionCounts: $resolvedSessionCounts,
                        weightProgression: $weightProgression,
                        maxHR: $planMaxHR,
                        iatPercent: $planIatPercent,
                    ),
                );

                $plannedExercises = collect($plannedSession->exercises)
                    ->map(function (ResolvedPlannedExercise $plannedExercise, int $index) use ($sourceExercisesBySignature, $groupLabels): ?array {
                        $exercise = $sourceExercisesBySignature->get($this->exerciseSignature(
                            exerciseId: (int) $plannedExercise->exerciseId,
                            sort: $plannedExercise->sort,
                            group: $plannedExercise->group,
                            type: $plannedExercise->type,
                        ));

                        if (! $exercise instanceof Exercise) {
                            return null;
                        }

                        $programExerciseId = (int) $exercise->pivot->id;

                        return [
                            'type' => $plannedExercise->type,
                            'view' => $this->exerciseViewBuilder->build(
                                $exercise,
                                $plannedExercise,
                                $index,
                                $groupLabels[$programExerciseId] ?? null,
                            ),
                        ];
                    })
                    ->filter()
                    ->values();

                $exercisesBySection = [];
                foreach (self::SECTION_ORDER as $section) {
                    $exercisesBySection[$section] = $plannedExercises
                        ->filter(fn (array $exercise): bool => $exercise['type'] === $section)
                        ->pluck('view')
                        ->values()
                        ->all();
                }

                $sectionTabs = collect(self::SECTION_ORDER)
                    ->map(function (string $section) use ($exercisesBySection): ?array {
                        $count = count($exercisesBySection[$section] ?? []);

                        if ($count === 0) {
                            return null;
                        }

                        return [
                            'key' => $section,
                            'label' => match ($section) {
                                'warm_up' => 'Warm Up',
                                'warm_down' => 'Cool Down',
                                default => 'Program',
                            },
                            'count' => $count,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                $progressSegments = collect($exercisesBySection)
                    ->flatten(1)
                    ->map(fn (ProgramDetailsExerciseData $exercise): array => [
                        'submitted' => false,
                        'color' => $exercise->statusColor,
                    ])
                    ->values()
                    ->all();

                $program = new ScheduledProgramData(
                    slotId: $sessionNumber,
                    trainingProgramId: $exerciseProgram->id,
                    name: $exerciseProgram->name,
                    time: $timeLabel,
                    categoryName: $exerciseProgram->exerciseCategory?->name,
                    categoryShortName: $exerciseProgram->exerciseCategory?->short_name,
                    categoryColor: $exerciseProgram->exerciseCategory?->color,
                    period: 'am',
                    exerciseCount: count($exercisesBySection['main'] ?? []),
                    progressSegments: $progressSegments,
                    recordedExerciseCount: 0,
                    totalExerciseCount: count($progressSegments),
                    isFuture: $isFutureSession,
                    previewKey: $sessionKey,
                    periodLabel: 'S'.$sessionNumber,
                );

                $dayKey = $date ?? 'preview-week-'.$weekIndex;
                $days[$dayKey] ??= [
                    'date' => $dayKey,
                    'isFuture' => $isFutureSession,
                    'formattedDate' => $formattedDate,
                    'sessionCount' => 0,
                    'programs' => collect(),
                ];
                $days[$dayKey]['sessionCount']++;
                $days[$dayKey]['programs']->push($program);

                $sessions[$sessionKey] = [
                    'key' => $sessionKey,
                    'date' => $date ?? $dayKey,
                    'formattedDate' => $formattedDate,
                    'timeLabel' => $timeLabel,
                    'isFutureSession' => $isFutureSession,
                    'categoryLabel' => $exerciseProgram->exerciseCategory?->name ?: $exerciseProgram->exerciseCategory?->short_name,
                    'categoryColor' => $exerciseProgram->exerciseCategory?->color,
                    'progressSegments' => $progressSegments,
                    'sectionTabs' => $sectionTabs,
                    'exercisesBySection' => $exercisesBySection,
                ];

                $sessionNumber++;
            }
        }

        return [
            'days' => array_values($days),
            'sessions' => $sessions,
        ];
    }

    private function exerciseSignature(int $exerciseId, int $sort, ?string $group, string $type): string
    {
        return implode(':', [$exerciseId, $sort, $group ?? '', $type]);
    }
}
