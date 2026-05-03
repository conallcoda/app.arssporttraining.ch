<?php

use App\Data\Athlete\Metric\MetricEnum;
use App\Models\Athlete\MetricSubmission;
use App\Models\Users\User;
use App\Support\Training\MetricModalPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('builds an edit payload from existing metric data', function () {
    $builder = new MetricModalPayloadBuilder;

    $payload = $builder->fromExistingData([
        'id' => 42,
        'recorded_at' => '2026-03-05',
        'user_id' => 7,
    ], MetricEnum::OneRepMax);

    expect($payload)->toBe([
        'data' => [
            'id' => 42,
            'recorded_at' => '2026-03-05',
            'user_id' => 7,
            'metric' => MetricEnum::OneRepMax->value,
        ],
        'title' => 'Edit Metric (1RM)',
    ]);
});

it('builds an edit payload from a submission model', function () {
    $athlete = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();

    $submission = MetricSubmission::query()->create([
        'user_id' => $athlete->id,
        'metric' => MetricEnum::HeartRate,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-03-08',
    ]);
    $submission->values()->createMany([
        ['field' => 'heartRate', 'value' => '185'],
        ['field' => 'anaerobicThreshold', 'value' => '88'],
    ]);

    $builder = new MetricModalPayloadBuilder;
    $payload = $builder->fromSubmission($submission, MetricEnum::HeartRate);

    expect($payload['data']['id'])->toBe($submission->id)
        ->and($payload['data']['metric'])->toBe(MetricEnum::HeartRate->value)
        ->and($payload['title'])->toBe('Edit Metric (Heart Rate)');
});

it('builds a creation payload for a single athlete', function () {
    $builder = new MetricModalPayloadBuilder;

    $payload = $builder->forCreation(MetricEnum::OneRepMax, '2026-03-10', 12);

    expect($payload)->toBe([
        'data' => [
            'metric' => MetricEnum::OneRepMax->value,
            'recorded_at' => '2026-03-10',
            'user_id' => 12,
        ],
        'title' => 'Add Metric (1RM)',
    ]);
});

it('builds a group creation payload with available athletes', function () {
    $builder = new MetricModalPayloadBuilder;

    $payload = $builder->forGroupCreation(MetricEnum::HeartRate, '2026-03-10', [
        ['id' => 1, 'name' => 'Alice Able'],
        ['id' => 2, 'name' => 'Bob Baker'],
    ]);

    expect($payload)->toBe([
        'data' => [
            'metric' => MetricEnum::HeartRate->value,
            'recorded_at' => '2026-03-10',
            'user_id' => null,
            '_group_mode' => true,
            '_available_athletes' => [
                ['id' => 1, 'name' => 'Alice Able'],
                ['id' => 2, 'name' => 'Bob Baker'],
            ],
        ],
        'title' => 'Add Metric (Heart Rate)',
    ]);
});
