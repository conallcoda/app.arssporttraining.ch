<?php

use App\Models\TrainingPlan;
use App\Support\WeekOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sets the default start date to the current week when not set', function () {
    $plan = TrainingPlan::create(['name' => 'Test Plan']);

    expect($plan->config->defaultScheduleStartDate())
        ->toBe(WeekOptions::getCurrentWeekValue());
});

it('does not overwrite an existing default start date', function () {
    $plan = TrainingPlan::create(['name' => 'Test Plan']);

    $config = $plan->config;
    $config->setDefaultScheduleStartDate('2026-01-05');
    $plan->config = $config;
    $plan->save();

    $plan->refresh();

    expect($plan->config->defaultScheduleStartDate())
        ->toBe('2026-01-05');
});

it('sets start date on existing plans with empty start date when saved', function () {
    $plan = new TrainingPlan;
    $plan->name = 'Legacy Plan';
    $plan->setRawAttributes([
        'name' => 'Legacy Plan',
        'config' => json_encode([
            'default' => [
                'schedule' => [
                    'weeks' => [],
                    'startDate' => '',
                ],
                'target' => [
                    'measuredReps' => 1,
                    'measuredWeight' => 50,
                    'targetGoal' => 10,
                ],
            ],
        ]),
    ]);
    $plan->save();

    $plan->refresh();

    expect($plan->config->defaultScheduleStartDate())
        ->toBe(WeekOptions::getCurrentWeekValue());
});
