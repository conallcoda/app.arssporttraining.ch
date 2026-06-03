<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

it('groups laravel log errors by message text and tracks first and last seen dates', function () {
    $path = storage_path('logs/test-laravel-prod.log');

    File::put($path, implode("\n", [
        '[2026-05-31 12:00:00] production.ERROR: SQLSTATE[HY000] [2002] Connection refused {"userId":1,"request_id":"abc"}',
        '[stacktrace]',
        '#0 /var/task/vendor/laravel/framework/src/Illuminate/Database/Connection.php(1): example',
        '[2026-06-02 09:15:00] production.ERROR: App\\Exceptions\\MissingMetricException: Missing metric zone {"userId":99}',
        '[stacktrace]',
        '[2026-06-01 14:30:00] production.ERROR: SQLSTATE[HY000] [2002] Connection refused {"userId":2,"request_id":"def"}',
        '[2026-06-03 07:30:00] production.ERROR: SQLSTATE[HY000] [2002] Connection refused {"userId":3,"exception":"[object] (Exception(code: 0): unfinished context',
        '[2026-06-03 08:00:00] production.WARNING: This warning should be ignored {"userId":1}',
        '',
    ]));

    $exitCode = Artisan::call('logs:errors', ['--path' => $path, '--full' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Unique log messages: 2')
        ->and($output)->toContain('App\\Exceptions\\MissingMetricException: Missing metric zone')
        ->and($output)->toContain('SQLSTATE[HY000] [2002] Connection refused')
        ->and($output)->toContain('2026-05-31 12:00:00')
        ->and($output)->toContain('2026-06-03 07:30:00')
        ->and($output)->toContain('2026-06-02 09:15:00');
});

it('can include every laravel log level when requested', function () {
    $path = storage_path('logs/test-laravel-prod.log');

    File::put($path, implode("\n", [
        '[2026-06-03 08:00:00] production.WARNING: A warning message {"userId":1}',
        '[2026-06-03 09:00:00] production.ERROR: An error message {"userId":2}',
        '',
    ]));

    $exitCode = Artisan::call('logs:errors', ['--path' => $path, '--level' => 'all']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Unique log messages: 2')
        ->and($output)->toContain('A warning message')
        ->and($output)->toContain('An error message');
});
