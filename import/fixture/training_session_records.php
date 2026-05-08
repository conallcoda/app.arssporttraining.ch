<?php

$slotId = 601;
$strengthSlotIds = [];

$scheduleFor = function (int $userId, int $week): array {
    $pattern = [
        'strength' => [0, 3],
        'endurance' => [1],
        'ski' => [5],
    ];

    if ($userId === 2 && $week === 3) {
        $pattern['endurance'] = [2];
    }

    if ($userId === 2 && $week === 6) {
        $pattern['ski'] = [6];
    }

    if ($userId === 3 && $week === 1) {
        $pattern['strength'] = [0, 4];
    }

    if ($userId === 3 && $week === 5) {
        $pattern['ski'] = [4];
    }

    if ($userId === 4 && $week === 2) {
        $pattern['strength'] = [1, 3];
    }

    if ($userId === 4 && $week === 4) {
        $pattern['endurance'] = [2];
    }

    return $pattern;
};

foreach ([2, 3, 4] as $userId) {
    for ($week = 0; $week < 8; $week++) {
        foreach ($scheduleFor($userId, $week)['strength'] ?? [] as $dayOffset) {
            if ($week < 4) {
                $strengthSlotIds[$userId][$week][] = $slotId;
            }

            $slotId++;
        }
    }
}

$records = [];

foreach ($strengthSlotIds as $userWeeks) {
    foreach ($userWeeks as $sessionIds) {
        foreach ($sessionIds as $sessionId) {
            $records[$sessionId] = [
                'slot_id' => $sessionId,
                'default_state' => 'completed',
                'exercise_overrides' => [],
            ];
        }
    }
}

$records[$strengthSlotIds[2][0][0]]['exercise_overrides'] = [
    [
        'name' => 'Strength - Coach Fixed Weight',
        'sets' => [
            ['number' => 1, 'state' => 'completed', 'values' => ['weight' => 7.5]],
            ['number' => 2, 'state' => 'completed', 'values' => ['weight' => 7.5]],
            ['number' => 3, 'state' => 'completed', 'values' => ['weight' => 5]],
            ['number' => 4, 'state' => 'completed', 'values' => ['weight' => 5]],
        ],
    ],
    [
        'name' => 'Strength - Athlete Enters Weight',
        'sets' => [
            ['number' => 1, 'state' => 'completed', 'values' => ['reps' => '9', 'weight' => 22.5]],
            ['number' => 2, 'state' => 'completed', 'values' => ['reps' => '8', 'weight' => 25]],
            ['number' => 3, 'state' => 'completed', 'values' => ['reps' => '8', 'weight' => 27.5]],
        ],
    ],
];

$records[$strengthSlotIds[2][1][1]]['exercise_overrides'] = [
    [
        'name' => 'Strength - Coach Fixed Weight',
        'sets' => [
            ['number' => 4, 'state' => 'pending'],
        ],
    ],
    [
        'name' => 'Strength - Athlete Enters Weight',
        'sets' => [
            ['number' => 1, 'state' => 'completed', 'values' => ['weight' => 20]],
            ['number' => 2, 'state' => 'completed', 'values' => ['weight' => 20]],
            ['number' => 3, 'state' => 'completed', 'values' => ['weight' => 22.5]],
        ],
    ],
];

$records[$strengthSlotIds[3][1][0]] = [
    'slot_id' => $strengthSlotIds[3][1][0],
    'default_state' => 'skipped',
    'exercise_overrides' => [],
];

$records[$strengthSlotIds[4][2][1]]['exercise_overrides'] = [
    [
        'name' => 'Strength - 1RM 110%',
        'state' => 'skipped',
    ],
    [
        'name' => 'Strength - Coach Fixed Weight',
        'sets' => [
            ['number' => 1, 'state' => 'completed', 'values' => ['weight' => 6]],
        ],
    ],
    [
        'name' => 'Strength - Athlete Enters Weight',
        'sets' => [
            ['number' => 1, 'state' => 'completed', 'values' => ['weight' => 30]],
            ['number' => 2, 'state' => 'completed', 'values' => ['weight' => 32.5]],
            ['number' => 3, 'state' => 'completed', 'values' => ['weight' => 35]],
        ],
    ],
];

return array_values($records);
