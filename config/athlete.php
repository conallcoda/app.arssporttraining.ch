<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Athlete Dashboard Today Override
    |--------------------------------------------------------------------------
    |
    | Set a fixed dashboard "today" in d.m.Y format for athlete dashboard views.
    | Set to null to use the actual current date.
    |
    */
    'dashboard_today_override' => '30.04.2026',

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
    | Allow Readiness For Past Days
    |--------------------------------------------------------------------------
    |
    | When true, readiness behaves like the current setup and is only shown
    | for the dashboard "today" date. When false, readiness can also be
    | shown and submitted for past days, but never for future dates.
    |
    */
    'allow_readiness_past' => true,

];
