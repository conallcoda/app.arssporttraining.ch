<?php

use App\Support\Training\GridOverrideNormalizer;

it('prunes overrides outside the current week session topology', function () {
    $pruned = GridOverrideNormalizer::pruneToSessionCounts([
        'sessions' => [
            ['week' => 0, 'session' => 0, 'data' => ['sets' => 3]],
            ['week' => 0, 'session' => 1, 'data' => ['sets' => 5]],
            ['week' => 2, 'session' => 0, 'data' => ['sets' => 4]],
        ],
        'cells' => [
            ['week' => 1, 'session' => 0, 'set' => 0, 'data' => ['reps' => '10-12']],
            ['week' => 1, 'session' => 1, 'set' => 0, 'data' => ['reps' => '8-10']],
            ['week' => 2, 'session' => 0, 'set' => 0, 'data' => ['reps' => '6-8']],
        ],
    ], [
        0 => 1,
        1 => 1,
    ]);

    expect($pruned)->toBe([
        'sessions' => [
            ['week' => 0, 'session' => 0, 'data' => ['sets' => 3]],
        ],
        'cells' => [
            ['week' => 1, 'session' => 0, 'set' => 0, 'data' => ['reps' => '10-12']],
        ],
    ]);
});
