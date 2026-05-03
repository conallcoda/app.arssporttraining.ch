<?php

use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Support\Training\SlotStatusPresenter;
use Tests\TestCase;

uses(TestCase::class);

it('maps enum and string statuses to colors', function () {
    $presenter = new SlotStatusPresenter;

    expect($presenter->color(TrainingProgramSlotStatusEnum::Completed))->toBe([
        'light' => '110 231 183',
        'dark' => '52 211 153',
    ])->and($presenter->color('skipped'))->toBe([
        'light' => '125 211 252',
        'dark' => '56 189 248',
    ]);
});

it('resolves aggregate statuses with the controller precedence', function () {
    $presenter = new SlotStatusPresenter;

    expect($presenter->aggregateStatus([
        'completed' => 0,
        'partial' => 0,
        'skipped' => 0,
        'pending' => 2,
    ]))->toBe('pending')
        ->and($presenter->aggregateStatus([
            'completed' => 0,
            'partial' => 0,
            'skipped' => 2,
            'pending' => 0,
        ]))->toBe('skipped')
        ->and($presenter->aggregateStatus([
            'completed' => 1,
            'partial' => 1,
            'skipped' => 0,
            'pending' => 0,
        ]))->toBe('partially_completed')
        ->and($presenter->aggregateStatus([
            'completed' => 1,
            'partial' => 0,
            'skipped' => 1,
            'pending' => 0,
        ]))->toBe('completed');
});

it('derives aggregate colors from mixed status values', function () {
    $presenter = new SlotStatusPresenter;

    expect($presenter->aggregateColor([
        TrainingProgramSlotStatusEnum::Completed,
        'partially_completed',
        null,
    ]))->toBe([
        'light' => '252 211 77',
        'dark' => '251 191 36',
    ]);
});
