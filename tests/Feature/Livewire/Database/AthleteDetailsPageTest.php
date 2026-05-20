<?php

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\Metrics\HeartRateMetric;
use App\Data\Athlete\Metric\Metrics\OneRepMaxMetric;
use App\Data\Athlete\Metric\Metrics\ReadinessMetric;
use App\Data\Athlete\Metric\MetricSubmissionData;
use App\Models\Users\User;
use App\Support\AthleteMetrics\OneRepMaxExamplePreviewBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the athlete details page with the new top level tabs', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create([
        'forename' => 'Joe',
        'surname' => 'Bloggs',
        'owner_id' => $coach->id,
        'email' => 'joe@example.com',
        'phone' => '12345',
    ]);

    $this->actingAs($coach)
        ->get(route('athlete-details', ['record' => $athlete->id]))
        ->assertOk()
        ->assertSee('General')
        ->assertSee('Readiness')
        ->assertSee('Heart Rate')
        ->assertSee('One Rep Max')
        ->assertSee('Setup Status')
        ->assertSee('Send Setup Email')
        ->assertSee('Change Password')
        ->assertSee('Joe')
        ->assertSee('Bloggs');
});

it('renders the readiness tab with the current summary and breakdown', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();

    storeAthleteMetric(
        athlete: $athlete,
        coach: $coach,
        metric: MetricEnum::Readiness,
        recordedAt: '2026-04-29',
        data: new ReadinessMetric(
            sleepMinutes: 510,
            sleepQuality: 5,
            altitudeMeters: 1500,
            condition: 5,
            mood: 5,
            motivation: 5,
            soreness: 4,
            energy: 5,
            restingHeartRate: 54,
            restingHeartRateBaseline: 56,
            hrv: 72,
        ),
    );

    $this->actingAs($coach)
        ->get(route('athlete-details', ['record' => $athlete->id, 'tab' => 'readiness']))
        ->assertOk()
        ->assertSee('Preview')
        ->assertSee('Summary')
        ->assertSee('Score Breakdown')
        ->assertSee('29.04.2026');
});

it('renders the heart rate tab with bike and jogging previews', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();

    storeAthleteMetric(
        athlete: $athlete,
        coach: $coach,
        metric: MetricEnum::HeartRate,
        recordedAt: '2026-04-29',
        data: new HeartRateMetric(
            heartRate: 193,
            anaerobicThreshold: 90,
        ),
    );

    $this->actingAs($coach)
        ->get(route('athlete-details', ['record' => $athlete->id, 'tab' => 'heart_rate']))
        ->assertOk()
        ->assertSee('Bike Preview')
        ->assertSee('Jogging Preview')
        ->assertSee('97 - 124 bpm')
        ->assertSee('106 - 134 bpm')
        ->assertSee('80% - 90%')
        ->assertSee('85% - 95%');
});

it('renders the one rep max tab with a five week strength preview', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();

    storeAthleteMetric(
        athlete: $athlete,
        coach: $coach,
        metric: MetricEnum::OneRepMax,
        recordedAt: '2026-04-29',
        data: new OneRepMaxMetric(
            measuredReps: 2,
            measuredWeight: 120,
        ),
    );

    $this->actingAs($coach)
        ->get(route('athlete-details', ['record' => $athlete->id, 'tab' => 'one_rep_max']))
        ->assertOk()
        ->assertSee('Preview')
        ->assertDontSee('Preview Goal')
        ->assertSee('Recorded')
        ->assertSee('Current 1RM')
        ->assertSee('Measured Set')
        ->assertSee('Session')
        ->assertDontSee('Tempo')
        ->assertDontSee('Rest (s)');
});

it('applies the selected preview goal when building the 1rm example grid', function () {
    $grid = app(OneRepMaxExamplePreviewBuilder::class)->build(
        new OneRepMaxMetric(
            measuredReps: 1,
            measuredWeight: 50,
        ),
        targetGoal: 20,
    );

    expect($grid)->not->toBeNull()
        ->and($grid?->summary['targetGoal'])->toBe(20);
});

function storeAthleteMetric(
    User $athlete,
    User $coach,
    MetricEnum $metric,
    string $recordedAt,
    mixed $data,
): void {
    (new MetricSubmissionData(
        user_id: $athlete->id,
        metric: $metric,
        recorded_by: $coach->id,
        recorded_at: $recordedAt,
        data: $data,
    ))->persist();
}
