<?php

use App\Training\ExerciseGroupLabeler;

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
