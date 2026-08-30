<?php

use App\Data\Exercise\Preview\ExercisePreviewBuilder;
use App\Data\Exercise\Preview\GridOverrides;
use App\Data\Training\Config\ExerciseOverrides;
use Tests\TestCase;

uses(TestCase::class);

it('attaches shared planned provenance to preview rows and columns', function () {
    $grid = ExercisePreviewBuilder::build(
        data: [
            'settings' => ['reps', 'rest'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
            'rest' => ['default' => 75, 'applyPer' => 'week'],
        ],
        weeks: 1,
        overrides: GridOverrides::fromConfig([
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

it('shows resolved set counts for locked sessions in the sets column', function () {
    $grid = ExercisePreviewBuilder::build(
        data: [
            'settings' => ['reps'],
            'sets' => ['default' => 3, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
        ],
        weeks: 1,
        sessionsPerWeek: 2,
        weekSessionDates: [
            ['2026-04-10', '2026-04-11'],
        ],
        lockedSessionsByWeek: [
            [true, true],
        ],
    );

    $setsColumn = collect($grid->weekColumns)->firstWhere('field', 'sets');

    expect($setsColumn)->not->toBeNull()
        ->and($setsColumn->presentWeekCell(0, 0)['value'])->toBe(3)
        ->and($setsColumn->presentWeekCell(0, 1)['value'])->toBe(3)
        ->and($setsColumn->cells[0] ?? null)->toBe(3);
});

it('displays fixed session groups from canonical chronological slot coordinates', function () {
    $grid = ExercisePreviewBuilder::build(
        data: [
            'settings' => ['reps'],
            'sets' => ['default' => 3, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 10, 'applyPer' => 'set'],
            'preview' => [
                'weeks' => 5,
                'sessionsPerWeek' => 2,
                'groupingMode' => 'groups',
                'groupSize' => 2,
            ],
        ],
        weeks: 10,
        overrides: GridOverrides::fromConfig([
            'sessions' => [],
            'cells' => collect([
                [2, 0, 8],
                [2, 1, 8],
                [3, 0, 8],
                [3, 1, 8],
                [4, 0, 6],
                [4, 1, 6],
            ])->flatMap(fn (array $coordinate): array => collect(range(0, 2))
                ->map(fn (int $set): array => [
                    'week' => $coordinate[0],
                    'session' => $coordinate[1],
                    'set' => $set,
                    'data' => ['reps' => $coordinate[2]],
                ])
                ->all())
                ->all(),
        ]),
        sessionsPerWeek: 1,
        weekSessionDates: collect(range(1, 10))
            ->map(fn (int $day): array => [sprintf('2026-09-%02d', $day)])
            ->all(),
        explicitWeekSessionCounts: array_fill(0, 10, 1),
        useSlotIndexForGroupedSessions: true,
    );

    $repsRow = collect($grid->rows)->firstWhere('field', 'reps');
    $expectedBySlot = [10, 10, 10, 10, 8, 8, 8, 8, 6, 6];

    foreach ($expectedBySlot as $slot => $expected) {
        foreach (range(0, 2) as $set) {
            expect($repsRow->getCellValue($slot, $set, 0))->toBe($expected, "slot {$slot}, set {$set}");
        }
    }
});
