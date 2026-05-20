<?php

use App\Support\Training\ProgramExerciseOrder;

it('sorts rows by group and then by sort with ungrouped rows first', function () {
    $sorted = app(ProgramExerciseOrder::class)->sortRows([
        ['id' => 1, 'group' => 'B', 'sort' => 0],
        ['id' => 2, 'group' => null, 'sort' => 0],
        ['id' => 3, 'group' => 'A', 'sort' => 1],
        ['id' => 4, 'group' => 'A', 'sort' => 0],
    ])->pluck('id')->all();

    expect($sorted)->toBe([2, 4, 3, 1]);
});

it('normalizes row sort values within each group', function () {
    $rows = app(ProgramExerciseOrder::class)->normalizeRows([
        ['id' => 10, 'group' => 'B', 'sort' => 0],
        ['id' => 11, 'group' => 'A', 'sort' => 0],
        ['id' => 12, 'group' => 'A', 'sort' => 0],
        ['id' => 13, 'group' => null, 'sort' => 0],
    ]);

    expect(collect($rows)->map(fn (array $row) => [
        'id' => $row['id'],
        'group' => $row['group'],
        'sort' => $row['sort'],
    ])->all())->toBe([
        ['id' => 13, 'group' => null, 'sort' => 0],
        ['id' => 11, 'group' => 'A', 'sort' => 0],
        ['id' => 12, 'group' => 'A', 'sort' => 1],
        ['id' => 10, 'group' => 'B', 'sort' => 0],
    ]);
});

it('does not allow moves across group boundaries', function () {
    $service = app(ProgramExerciseOrder::class);
    $rows = $service->normalizeRows([
        ['id' => 10, 'group' => 'A', 'sort' => 0],
        ['id' => 11, 'group' => 'B', 'sort' => 0],
    ]);

    expect($service->canMoveRow($rows, 1, -1))->toBeFalse()
        ->and($service->moveRow($rows, 1, -1))->toBe($rows);
});
