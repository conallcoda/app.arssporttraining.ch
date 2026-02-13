<?php

use App\Data\Training\Config\DefaultTrainingPlanConfig;
use App\Data\Training\Config\Schedule\DefaultScheduleConfig;
use App\Data\Training\Config\Schedule\ScheduleWeek;
use App\Data\Training\Config\Schedule\ScheduleWeekSlot;
use App\Data\Training\Config\TrainingPlanConfig;
use App\Data\Training\Config\UserTrainingPlanConfig;
use Spatie\LaravelData\Optional;

function sampleConfig(): array
{
    return json_decode(file_get_contents(base_path('a.json')), true);
}

it('deserializes from the full JSON config', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    expect($config)->toBeInstanceOf(TrainingPlanConfig::class);
    expect($config->default)->toBeInstanceOf(DefaultTrainingPlanConfig::class);
    expect($config->users)->toBeArray();
});

it('deserializes default schedule with start date and weeks', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $schedule = $config->default->schedule;

    expect($schedule)->toBeInstanceOf(DefaultScheduleConfig::class);
    expect($schedule->startDate)->toBe('2026-02-02');
    expect($schedule->weeks)->toHaveCount(5);
});

it('deserializes schedule weeks as typed ScheduleWeek objects', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $firstWeek = $config->default->schedule->weeks[0];

    expect($firstWeek)->toBeInstanceOf(ScheduleWeek::class);
    expect($firstWeek->id)->toBe('default_0');
    expect($firstWeek->linkedTo)->toBeNull();
    expect($firstWeek->sort)->toBe(0);
    expect($firstWeek->slots)->toHaveCount(3);
});

it('deserializes linked weeks', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $secondWeek = $config->default->schedule->weeks[1];

    expect($secondWeek)->toBeInstanceOf(ScheduleWeek::class);
    expect($secondWeek->id)->toBe('default_1');
    expect($secondWeek->linkedTo)->toBe('default_0');
    expect($secondWeek->slots)->toBeEmpty();
    expect($secondWeek->sort)->toBe(1);
});

it('deserializes schedule week slots as typed objects with isLinked flag', function () {
    $config = TrainingPlanConfig::from(sampleConfig());
    $slots = $config->default->schedule->weeks[0]->slots;

    $regularSlot = collect($slots)->firstWhere('day', 0);
    expect($regularSlot)->toBeInstanceOf(ScheduleWeekSlot::class);
    expect($regularSlot->programId)->toBe(1);
    expect($regularSlot->isLinked)->toBeInstanceOf(Optional::class);

    $linkedSlot = collect($slots)->firstWhere('day', 4);
    expect($linkedSlot)->toBeInstanceOf(ScheduleWeekSlot::class);
    expect($linkedSlot->programId)->toBe(1);
    expect($linkedSlot->isLinked)->toBeTrue();
});

it('returns null for non-existent user', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    expect($config->forUser(999))->toBeNull();
});

it('returns typed UserTrainingPlanConfig via forUser accessor', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $userConfig = $config->forUser(5);

    expect($userConfig)->toBeInstanceOf(UserTrainingPlanConfig::class);
});

it('can be created with minimal data', function () {
    $config = TrainingPlanConfig::from([
        'default' => [],
    ]);

    expect($config)->toBeInstanceOf(TrainingPlanConfig::class);
    expect($config->default->schedule)->toBeInstanceOf(Optional::class);
    expect($config->users)->toBeEmpty();
});

it('can be created via initialize factory', function () {
    $config = TrainingPlanConfig::initialize();

    expect($config)->toBeInstanceOf(TrainingPlanConfig::class);
    expect($config->defaultScheduleWeeks())->toBeEmpty();
});

it('provides typed accessor for default schedule weeks', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $weeks = $config->defaultScheduleWeeks();

    expect($weeks)->toHaveCount(5);
});
