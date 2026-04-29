<?php

use App\Data\Athlete\Metric\MetricSubmissionData;
use App\Livewire\Database\AthleteMetricFormModal;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the shared readiness form inside the athlete metric modal', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();

    Livewire::actingAs($coach)
        ->test(AthleteMetricFormModal::class, [
            'name' => 'calendar-metric-form',
            'title' => 'Add Metric',
            'formDataClass' => MetricSubmissionData::class,
        ])
        ->call('open', [
            'metric' => 'readiness',
            'user_id' => $athlete->id,
            'recorded_at' => '2026-04-29',
        ], 'Add Metric (Readiness)')
        ->assertSee('How long did you sleep last night?')
        ->assertDontSee('Readiness Score');
});

it('renders live bike and jogging previews inside the heart rate metric modal', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();

    Livewire::actingAs($coach)
        ->test(AthleteMetricFormModal::class, [
            'name' => 'calendar-metric-form',
            'title' => 'Add Metric',
            'formDataClass' => MetricSubmissionData::class,
        ])
        ->call('open', [
            'metric' => 'heartRate',
            'user_id' => $athlete->id,
            'recorded_at' => '2026-04-29',
            'data' => [
                'heartRate' => 193,
                'anaerobicThreshold' => 90,
            ],
        ], 'Add Metric (Heart Rate)')
        ->assertSee('Bike Preview')
        ->assertSee('Jogging Preview')
        ->assertSee('97 - 124 bpm')
        ->assertSee('106 - 134 bpm')
        ->assertSee('80% - 90%')
        ->assertSee('85% - 95%');
});
