<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allow All Athlete Editing
    |--------------------------------------------------------------------------
    |
    | Master toggle for athlete-entered data across date-sensitive athlete
    | flows. Set to true to allow editing for past, today, and future dates by
    | default. Set to false to deny all of them by default. Set to null to keep
    | each feature's built-in default unless overridden below.
    |
    */
    'can_edit_all' => true,

    /*
    |--------------------------------------------------------------------------
    | Athlete Editability Overrides
    |--------------------------------------------------------------------------
    |
    | Granular date-based overrides for specific athlete flows. Any value left
    | as null falls back to can_edit_all when present, otherwise to the
    | built-in default for that feature.
    |
    */
    'editability' => [
        'readiness' => [
            'past' => null,
            'today' => null,
            'future' => null,
        ],
        'programs' => [
            'exercises' => [
                'past' => null,
                'today' => null,
                'future' => null,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Athlete Dashboard Today Override
    |--------------------------------------------------------------------------
    |
    | Set a fixed dashboard "today" in d.m.Y format for athlete dashboard views.
    | Set to null to use the actual current date.
    |
    */
    'dashboard_today_override' => null,

    /*
    |--------------------------------------------------------------------------
    | Require Readiness Before Showing Training
    |--------------------------------------------------------------------------
    |
    | When enabled, the athlete dashboard will hide training programs until
    | readiness has been filled in. Set to false to always show training.
    |
    */
    'require_readiness_for_training_visibility' => true,

    /*
    |--------------------------------------------------------------------------
    | Allow Athlete Exercise Value Editing
    |--------------------------------------------------------------------------
    |
    | When enabled, athletes can edit exercise set values from the athlete
    | dashboard before and after marking an exercise done.
    |
    */
    'allow_athlete_edits' => true,

];
