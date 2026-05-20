<?php

use App\Training\ExerciseGroupLabeler;
use Coda\Cms\Support\ColorPalette;

it('uses plain group letters for single-item groups and numbered labels for repeated groups', function () {
    $items = [
        (object) ['id' => 10, 'group' => 'A'],
        (object) ['id' => 11, 'group' => 'B'],
        (object) ['id' => 12, 'group' => 'B'],
        (object) ['id' => 13, 'group' => null],
        (object) ['id' => 14, 'group' => 'C'],
    ];

    $labels = ExerciseGroupLabeler::label(
        $items,
        fn (object $item): ?string => $item->group,
        fn (object $item): int => $item->id,
    );

    expect($labels)->toBe([
        10 => 'A',
        11 => 'B1',
        12 => 'B2',
        14 => 'C',
    ]);
});

it('assigns stable palette colors per unique group while leaving ungrouped items uncolored', function () {
    $items = [
        (object) ['id' => 10, 'group' => 'B'],
        (object) ['id' => 11, 'group' => null],
        (object) ['id' => 12, 'group' => 'A'],
        (object) ['id' => 13, 'group' => 'B'],
        (object) ['id' => 14, 'group' => 'C'],
    ];

    $colors = ExerciseGroupLabeler::colors(
        $items,
        fn (object $item): ?string => $item->group,
        fn (object $item): int => $item->id,
    );

    expect($colors)->toBe([
        10 => ColorPalette::ROW_COLORS[1],
        12 => ColorPalette::ROW_COLORS[0],
        13 => ColorPalette::ROW_COLORS[1],
        14 => ColorPalette::ROW_COLORS[2],
    ]);
});
