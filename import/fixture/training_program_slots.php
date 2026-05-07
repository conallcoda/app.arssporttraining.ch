<?php

$start = now()->addDay()->setTime(9, 0, 0);
$archivedStart = now()->subMonth()->setTime(9, 0, 0);
$slots = [];
$slotId = 601;

$programIds = [
    'strength' => 301,
    'endurance' => 302,
    'ski' => 303,
    'archived_strength' => 304,
    'override_lab' => 305,
];

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
        $weekStart = $start->copy()->addWeeks($week);
        $pattern = $scheduleFor($userId, $week);

        foreach ($pattern as $programKey => $dayOffsets) {
            foreach ($dayOffsets as $dayOffset) {
                $datetime = $weekStart->copy()->addDays($dayOffset);

                $slots[] = [
                    'id' => $slotId++,
                    'training_program_id' => $programIds[$programKey],
                    'user_id' => $userId,
                    'owner_id' => null,
                    'datetime' => $datetime->toIso8601String(),
                ];
            }
        }
    }
}

foreach ([2, 3, 4] as $userId) {
    for ($week = 0; $week < 3; $week++) {
        $weekStart = $archivedStart->copy()->addWeeks($week);

        foreach ([0, 3] as $dayOffset) {
            $datetime = $weekStart->copy()->addDays($dayOffset);

            $slots[] = [
                'id' => $slotId++,
                'training_program_id' => $programIds['archived_strength'],
                'user_id' => $userId,
                'owner_id' => null,
                'datetime' => $datetime->toIso8601String(),
            ];
        }
    }
}

foreach ([2, 3, 4] as $userId) {
    for ($week = 0; $week < 8; $week++) {
        foreach ([0, 2] as $dayOffset) {
            $datetime = $start->copy()->addWeeks($week)->addDays($dayOffset);

            $slots[] = [
                'id' => $slotId++,
                'training_program_id' => $programIds['override_lab'],
                'user_id' => $userId,
                'owner_id' => null,
                'datetime' => $datetime->toIso8601String(),
            ];
        }
    }
}

return $slots;
