<?php

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\Metrics\OneRepMaxMetric;
use App\Data\Athlete\Metric\MetricSubmissionData;
use App\Models\Athlete\MetricSubmission;
use App\Models\Users\User;
use App\Training\Reference\OneRepMaxConversion;

it('creates a submission with EAV values', function () {
    $athlete = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();

    $data = new MetricSubmissionData(
        user_id: $athlete->id,
        metric: MetricEnum::OneRepMax,
        recorded_by: $coach->id,
        recorded_at: '2026-03-10',
        data: new OneRepMaxMetric(measuredReps: 5, measuredWeight: 100),
    );

    $submission = $data->persist();

    expect($submission)->toBeInstanceOf(MetricSubmission::class);
    expect($submission->user_id)->toBe($athlete->id);
    expect($submission->metric)->toBe(MetricEnum::OneRepMax);
    expect($submission->recorded_by)->toBe($coach->id);
    expect($submission->recorded_at->format('Y-m-d'))->toBe('2026-03-10');

    $values = $submission->values()->pluck('value', 'field')->all();
    expect($values)->toHaveKey('measuredReps', '5');
    expect($values)->toHaveKey('measuredWeight', '100');
    expect($values)->toHaveKey('estimated1RM');
});

it('calculates estimated 1RM correctly from derived values', function () {
    $athlete = User::factory()->athlete()->create();

    $data = new MetricSubmissionData(
        user_id: $athlete->id,
        recorded_at: '2026-03-10',
        data: new OneRepMaxMetric(measuredReps: 5, measuredWeight: 100),
    );

    $submission = $data->persist();

    $expected = OneRepMaxConversion::estimatedOneRepMax(5, 100);
    $stored = $submission->values()->where('field', 'estimated1RM')->value('value');

    expect((float) $stored)->toBe($expected);
});

it('hydrates from model via fromModel', function () {
    $athlete = User::factory()->athlete()->create();

    $original = new MetricSubmissionData(
        user_id: $athlete->id,
        recorded_at: '2026-03-10',
        data: new OneRepMaxMetric(measuredReps: 8, measuredWeight: 60),
    );

    $submission = $original->persist();
    $submission->load('values');

    $hydrated = MetricSubmissionData::fromModel($submission);

    expect($hydrated->id)->toBe($submission->id);
    expect($hydrated->user_id)->toBe($athlete->id);
    expect($hydrated->recorded_at)->toBe('2026-03-10');
    expect($hydrated->data)->toBeInstanceOf(OneRepMaxMetric::class);
    expect($hydrated->data->measuredReps)->toBe(8);
    expect($hydrated->data->measuredWeight)->toBe(60.0);
});

it('updates a submission and replaces old values', function () {
    $athlete = User::factory()->athlete()->create();

    $data = new MetricSubmissionData(
        user_id: $athlete->id,
        recorded_at: '2026-03-10',
        data: new OneRepMaxMetric(measuredReps: 5, measuredWeight: 100),
    );

    $submission = $data->persist();
    $originalValueCount = $submission->values()->count();

    $updated = new MetricSubmissionData(
        id: $submission->id,
        user_id: $athlete->id,
        recorded_at: '2026-03-10',
        data: new OneRepMaxMetric(measuredReps: 3, measuredWeight: 120),
    );

    $updated->persist();

    $submission->refresh()->load('values');
    $values = $submission->values()->pluck('value', 'field')->all();

    expect($values['measuredReps'])->toBe('3');
    expect($values['measuredWeight'])->toBe('120');
    expect($submission->values()->count())->toBe($originalValueCount);
});

it('soft deletes a submission', function () {
    $athlete = User::factory()->athlete()->create();

    $data = new MetricSubmissionData(
        user_id: $athlete->id,
        recorded_at: '2026-03-10',
        data: new OneRepMaxMetric(measuredReps: 5, measuredWeight: 100),
    );

    $submission = $data->persist();
    $submission->delete();

    expect(MetricSubmission::find($submission->id))->toBeNull();
    expect(MetricSubmission::withTrashed()->find($submission->id))->not->toBeNull();
});

it('scopes submissions by athlete and metric', function () {
    $athlete1 = User::factory()->athlete()->create();
    $athlete2 = User::factory()->athlete()->create();

    (new MetricSubmissionData(
        user_id: $athlete1->id,
        recorded_at: '2026-03-10',
        data: new OneRepMaxMetric(measuredReps: 5, measuredWeight: 100),
    ))->persist();

    (new MetricSubmissionData(
        user_id: $athlete2->id,
        recorded_at: '2026-03-10',
        data: new OneRepMaxMetric(measuredReps: 3, measuredWeight: 80),
    ))->persist();

    $results = MetricSubmission::query()
        ->forAthlete($athlete1->id)
        ->forMetric(MetricEnum::OneRepMax)
        ->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->user_id)->toBe($athlete1->id);
});

it('allows both coach and athlete to submit', function () {
    $athlete = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();

    $coachSubmission = (new MetricSubmissionData(
        user_id: $athlete->id,
        recorded_by: $coach->id,
        recorded_at: '2026-03-10',
        data: new OneRepMaxMetric(measuredReps: 5, measuredWeight: 100),
    ))->persist();

    $athleteSubmission = (new MetricSubmissionData(
        user_id: $athlete->id,
        recorded_by: $athlete->id,
        recorded_at: '2026-03-11',
        data: new OneRepMaxMetric(measuredReps: 3, measuredWeight: 110),
    ))->persist();

    expect($coachSubmission->recordedBy->id)->toBe($coach->id);
    expect($athleteSubmission->recordedBy->id)->toBe($athlete->id);
});

it('provides user metric submissions via relationship', function () {
    $athlete = User::factory()->athlete()->create();

    (new MetricSubmissionData(
        user_id: $athlete->id,
        recorded_at: '2026-03-10',
        data: new OneRepMaxMetric(measuredReps: 5, measuredWeight: 100),
    ))->persist();

    (new MetricSubmissionData(
        user_id: $athlete->id,
        recorded_at: '2026-03-11',
        data: new OneRepMaxMetric(measuredReps: 3, measuredWeight: 110),
    ))->persist();

    expect($athlete->metricSubmissions)->toHaveCount(2);
});
