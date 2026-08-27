<?php

use App\Support\Training\WeekSlotModalPayloadBuilder;
use Tests\TestCase;

uses(TestCase::class);

it('builds week slot modal payloads', function () {
    $builder = new WeekSlotModalPayloadBuilder;

    expect($builder->defaultStartTime('am'))->toBe('09:00')
        ->and($builder->defaultStartTime('pm'))->toBe('14:00');

    expect($builder->forCreate('2026-03-10', '09:00', 4, 8))->toBe([
        'date' => '2026-03-10',
        'start_time' => '09:00',
        'groupId' => 4,
        'userId' => 8,
    ]);

    expect($builder->forEdit(12, '2026-03-10', '14:00', 4, null))->toBe([
        'date' => '2026-03-10',
        'start_time' => '14:00',
        'training_program_id' => 12,
        'groupId' => 4,
        'userId' => null,
    ]);

    expect($builder->forProgramPrefill(12, '2026-03-10', 4, 8))->toBe([
        'date' => '2026-03-10',
        'start_time' => '09:00',
        'training_program_id' => 12,
        'groupId' => 4,
        'userId' => 8,
        'prefill' => true,
    ]);
});
