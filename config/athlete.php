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
    | When true, readiness can be shown and submitted for past days. Today's
    | readiness is always allowed regardless of this setting.
    |
    */
    'allow_readiness_past' => true,

    /*
    |--------------------------------------------------------------------------
    | Allow Readiness For Future Days
    |--------------------------------------------------------------------------
    |
    | When true, readiness can be shown and submitted for future days. Today's
    | readiness is always allowed regardless of this setting.
    |
    */
    'allow_readiness_future' => true,

];
