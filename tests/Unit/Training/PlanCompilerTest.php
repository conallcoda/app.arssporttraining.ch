<?php

use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Training\Compiler\AuthoringExerciseData;
use App\Data\Training\Compiler\AuthoringProgramData;
use App\Data\Training\Compiler\PlanningContextData;
use App\Training\Planning\PlanCompiler;

uses(Tests\TestCase::class);

it('builds a resolved planned session from authoring and planning dtos', function () {
    $program = new AuthoringProgramData(exercises: [
        new AuthoringExerciseData(
            exerciseId: 101,
            sort: 0,
            group: 'A1',
            type: 'main',
            effectiveConfig: [
                'settings' => ['reps', 'rest'],
                'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'set'],
                'rest' => ['default' => 60, 'applyPer' => 'session'],
                'preview' => ['weeks' => 1, 'sessionsPerWeek' => 1],
            ],
        ),
    ]);

    $context = new PlanningContextData(
        scheduledDate: '2026-04-27',
        weekIndex: 0,
        sessionIndex: 0,
        sessionsPerWeek: 1,
        weekSessionCounts: [1],
    );

    $session = app(PlanCompiler::class)->compile($program, $context);
    $exercise = $session->exercises[0];

    expect($session->scheduledDate)->toBe('2026-04-27')
        ->and($exercise->exerciseId)->toBe(101)
        ->and($exercise->group)->toBe('A1')
        ->and($exercise->sets)->toHaveCount(2)
        ->and(collect($exercise->sets[0]->values)->firstWhere('settingKey', 'reps')?->value)->toBe(12)
        ->and(collect($exercise->sets[0]->values)->firstWhere('settingKey', 'rest')?->value)->toBe(60)
        ->and(collect($exercise->sets[1]->values)->firstWhere('settingKey', 'rest')?->value)->toBe(60);
});

it('filters metric-dependent exercises at the pure compiler boundary', function () {
    $program = new AuthoringProgramData(exercises: [
        new AuthoringExerciseData(
            exerciseId: 201,
            sort: 0,
            group: null,
            type: 'main',
            effectiveConfig: [
                'settings' => ['reps', 'weight'],
                'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
                'weight' => ['mode' => 'automatic', 'oneRepMaxModifier' => 100, 'applyPer' => 'session'],
                'preview' => ['weeks' => 1, 'sessionsPerWeek' => 1],
            ],
        ),
        new AuthoringExerciseData(
            exerciseId: 202,
            sort: 1,
            group: null,
            type: 'main',
            effectiveConfig: [
                'settings' => ['reps'],
                'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 10, 'applyPer' => 'session'],
                'preview' => ['weeks' => 1, 'sessionsPerWeek' => 1],
            ],
        ),
    ]);

    $missingMetricContext = new PlanningContextData(
        scheduledDate: '2026-04-27',
        weekIndex: 0,
        sessionIndex: 0,
        sessionsPerWeek: 1,
        weekSessionCounts: [1],
        weightProgression: null,
    );

    $completeMetricContext = new PlanningContextData(
        scheduledDate: '2026-04-27',
        weekIndex: 0,
        sessionIndex: 0,
        sessionsPerWeek: 1,
        weekSessionCounts: [1],
        weightProgression: new WeightProgressionSetting(
            measuredReps: 1,
            measuredWeight: 93,
            targetGoal: 5,
        ),
    );

    $withoutMetrics = app(PlanCompiler::class)->compile($program, $missingMetricContext);
    $withMetrics = app(PlanCompiler::class)->compile($program, $completeMetricContext);

    expect(collect($withoutMetrics->exercises)->pluck('exerciseId')->all())->toBe([202])
        ->and(collect($withMetrics->exercises)->pluck('exerciseId')->all())->toBe([201, 202]);
});
