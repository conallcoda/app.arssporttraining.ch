<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Athlete Future Editing
    |--------------------------------------------------------------------------
    |
    | Master toggle for athlete-entered data on future dates. When false,
    | athlete edit flows are limited to today and past dates by default.
    |
    */
    'edit_future' => (bool) env('ATHLETE_EDIT_FUTURE', false),

    /*
    |--------------------------------------------------------------------------
    | Athlete User Switching
    |--------------------------------------------------------------------------
    |
    | Kept separate from admin user switching so the athlete dashboard can stay
    | locked to the authenticated athlete even when admin switching is enabled.
    |
    */
    'user_switching' => (bool) env('USER_SWITCHING_ATHLETE', false),

    /*
    |--------------------------------------------------------------------------
    | Legacy Athlete Editing Toggle
    |--------------------------------------------------------------------------
    |
    | Optional hard override retained for tests and direct config overrides.
    | Leave null to use the edit_future policy below.
    |
    */
    'can_edit_all' => null,

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
    'dashboard_today_override' => env('ATHLETE_DASHBOARD_TODAY_OVERRIDE'),

    /*
    |--------------------------------------------------------------------------
    | Require Readiness Before Showing Training
    |--------------------------------------------------------------------------
    |
    | When enabled, the athlete dashboard will hide training programs until
    | readiness has been filled in. Set to false to always show training.
    |
    */
    'require_readiness_for_training_visibility' => (bool) env('ATHLETE_REQUIRE_READINESS_FOR_TRAINING_VISIBILITY', true),

    /*
    |--------------------------------------------------------------------------
    | Allow Athlete Exercise Value Editing
    |--------------------------------------------------------------------------
    |
    | When enabled, athletes can edit exercise set values from the athlete
    | dashboard before and after marking an exercise done.
    |
    */
    'allow_athlete_edits' => (bool) env('ATHLETE_ALLOW_EXERCISE_VALUE_EDITING', true),

];
