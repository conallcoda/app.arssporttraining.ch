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

    /*
    |--------------------------------------------------------------------------
    | Account Setup Expiry Days
    |--------------------------------------------------------------------------
    |
    | Setup email links use a one-time token that expires after this many days.
    | Resending a setup email rotates the token and refreshes the expiry.
    |
    */
    'account_setup_expiry_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Account Setup Token Length
    |--------------------------------------------------------------------------
    |
    | Raw account setup tokens are generated with this many characters before
    | being hashed and stored against the athlete record.
    |
    */
    'account_setup_token_length' => 64,

];
