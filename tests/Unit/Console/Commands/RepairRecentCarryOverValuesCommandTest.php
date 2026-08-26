<?php

use App\Console\Commands\RepairRecentCarryOverValuesCommand;

it('includes the exact category and block in carry-over report links', function () {
    $command = app(RepairRecentCarryOverValuesCommand::class);
    $method = new ReflectionMethod($command, 'calendarUrl');
    $url = $method->invoke($command, [
        'group_id' => 14,
        'user_id' => 20,
        'plan_category_id' => 7,
        'plan_block_id' => 118,
        'training_program_id' => 174,
    ]);

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query)->toMatchArray([
        'group' => '14',
        'user' => '20',
        'planCategory' => '7',
        'planBlock' => '118',
        'planProgram' => '174',
        'view' => 'plan',
    ]);
});
