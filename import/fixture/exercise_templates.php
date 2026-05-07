<?php

return [
    [
        'id' => 2,
        'owner_id' => null,
        'name' => 'Interval Watt Bike',
        'config' => [
            'settings' => ['watts', 'duration', 'heartRateZone'],
            'overrides' => [
                'cells' => [],
                'weeks' => [],
            ],
            'sets' => [
                'deload' => 'none',
                'deloadBy' => 1,
                'label' => 'Interval',
                'default' => 5,
            ],
            'distance' => null,
            'duration' => [
                'unit' => 'seconds',
                'default' => 60,
                'applyPer' => 'session',
            ],
            'heartRate' => null,
            'heartRateZone' => [
                'default' => '3',
                'applyPer' => 'session',
            ],
            'note' => null,
            'pace' => null,
            'reps' => [
                'mode' => 'manual',
                'default' => 10,
                'stepDownInterval' => 2,
                'decrement' => 2,
                'minimum' => 1,
                'label' => '',
                'applyPer' => 'session',
            ],
            'rest' => [
                'default' => 60,
                'applyPer' => 'week',
            ],
            'tempo' => [
                'default' => '3010',
                'applyPer' => 'week',
            ],
            'watts' => [
                'default' => 100,
                'applyPer' => 'session',
            ],
            'weight' => [
                'mode' => 'manual',
                'oneRepMaxModifier' => 100,
                'default' => 5,
                'applyPer' => 'session',
            ],
            'preview' => [
                'weeks' => 1,
                'sessionsPerWeek' => 1,
                'measuredReps' => 1,
                'measuredWeight' => 50,
                'targetGoal' => 10,
            ],
        ],
        'deleted_at' => null,
    ],
    [
        'id' => 4,
        'owner_id' => null,
        'name' => 'Strength (Automatic)',
        'config' => [
            'settings' => ['reps', 'weight', 'tempo', 'rest'],
            'overrides' => [
                'cells' => [],
                'weeks' => [],
            ],
            'sets' => [
                'deload' => 'odd',
                'deloadBy' => 1,
                'label' => 'Set',
                'default' => 4,
            ],
            'distance' => null,
            'duration' => null,
            'heartRate' => null,
            'heartRateZone' => null,
            'note' => null,
            'pace' => null,
            'reps' => [
                'mode' => 'automatic',
                'default' => 10,
                'stepDownInterval' => 2,
                'decrement' => 2,
                'minimum' => 1,
                'label' => '',
                'applyPer' => 'session',
            ],
            'rest' => [
                'default' => 60,
                'applyPer' => 'week',
            ],
            'tempo' => [
                'default' => '3010',
                'applyPer' => 'week',
            ],
            'watts' => null,
            'weight' => [
                'mode' => 'automatic',
                'oneRepMaxModifier' => 100,
                'default' => 5,
                'applyPer' => 'session',
            ],
            'preview' => [
                'weeks' => 5,
                'sessionsPerWeek' => 1,
                'measuredReps' => 1,
                'measuredWeight' => 50,
                'targetGoal' => 10,
            ],
        ],
        'deleted_at' => null,
    ],
];
