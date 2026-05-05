<?php

return [
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
    | being hashed and stored against the user record.
    |
    */
    'account_setup_token_length' => 64,
];
