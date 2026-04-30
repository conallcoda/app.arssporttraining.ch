<?php

use App\Data\Exercise\Preview\ExercisePreviewBuilder;
use App\Data\Training\Config\ExerciseOverrides;

uses(Tests\TestCase::class);

it('attaches shared planned provenance to preview rows and columns', function () {
    $grid = ExercisePreviewBuilder::build(
        data: [
            'settings' => ['reps', 'rest'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
            'rest' => ['default' => 75, 'applyPer' => 'week'],
        ],
        weeks: 1,
        overrides: \App\Data\Exercise\Preview\GridOverrides::fromConfig([
            'sessions' => [],
            'cells' => [
                ['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 10]],
            ],
        ]),
        sessionsPerWeek: 1,
        baseConfig: [
            'settings' => ['reps', 'rest'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
            'rest' => ['default' => 60, 'applyPer' => 'week'],
        ],
        defaultOverrides: ExerciseOverrides::from([
            'rest' => ['default' => 75, 'applyPer' => 'week'],
        ]),
        userOverrides: ExerciseOverrides::from([
            'gridOverrides' => [
                'sessions' => [],
                'cells' => [
                    ['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 10]],
                ],
            ],
        ]),
    );

    $repsRow = collect($grid->rows)->firstWhere('field', 'reps');
    $restColumn = collect($grid->weekColumns)->firstWhere('field', 'rest');

    expect($repsRow)->not->toBeNull()
        ->and($restColumn)->not->toBeNull()
        ->and($repsRow->presentCell(0, 0, 0)['provenance']?->kind)->toBe('grid_override')
        ->and($repsRow->presentCell(0, 0, 0)['provenance']?->layer)->toBe('user')
        ->and($repsRow->presentCell(0, 0, 0)['overridden'] ?? null)->toBeTrue()
        ->and($restColumn->presentWeekCell(0, 0)['provenance']?->kind)->toBe('config')
        ->and($restColumn->presentWeekCell(0, 0)['provenance']?->layer)->toBe('plan');
});
