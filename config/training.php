<?php

use Carbon\Carbon;

return [
    'week_starts_on' => Carbon::MONDAY,

    'session_grouping' => [
        'default_mode' => 'groups',
        'default_group_size' => 2,
    ],
];
