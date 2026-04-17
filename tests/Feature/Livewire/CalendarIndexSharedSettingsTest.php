<?php

use App\Livewire\Training\CalendarIndex;
use App\Models\Users\User;
use App\Training\CalendarDateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('persists dynamic preset settings for all calendar views', function () {
    $coach = User::factory()->coach()->create();

    Carbon::setTestNow('2026-04-17 09:00:00');

    Livewire::actingAs($coach)
        ->test(CalendarIndex::class, ['view' => 'overview'])
        ->set('range', [
            'start' => '2026-04-17',
            'end' => '2026-07-16',
            'preset' => CalendarDateService::PRESET_NEXT_3_MONTHS,
        ])
        ->assertSet('start', '')
        ->assertSet('end', '');

    expect($coach->fresh()->config->get('calendar.settings'))->toMatchArray([
        'preset' => CalendarDateService::PRESET_NEXT_3_MONTHS,
        'start' => null,
        'end' => null,
    ]);

    Carbon::setTestNow('2026-04-24 09:00:00');

    Livewire::actingAs($coach)
        ->test(CalendarIndex::class, ['view' => 'schedule'])
        ->assertSet('preset', CalendarDateService::PRESET_NEXT_3_MONTHS)
        ->assertSet('start', '')
        ->assertSet('end', '');

    Carbon::setTestNow();
});

it('applies preset-only picker payloads immediately', function () {
    $coach = User::factory()->coach()->create();

    Carbon::setTestNow('2026-04-17 09:00:00');

    Livewire::actingAs($coach)
        ->test(CalendarIndex::class, ['view' => 'overview'])
        ->set('range', [
            'preset' => CalendarDateService::PRESET_NEXT_30_DAYS,
        ])
        ->assertSet('preset', CalendarDateService::PRESET_NEXT_30_DAYS)
        ->assertSet('start', '')
        ->assertSet('end', '');

    expect($coach->fresh()->config->get('calendar.settings'))->toMatchArray([
        'preset' => CalendarDateService::PRESET_NEXT_30_DAYS,
        'start' => null,
        'end' => null,
    ]);

    Carbon::setTestNow();
});

it('defaults to next 3 months when no calendar selection has been saved', function () {
    $coach = User::factory()->coach()->create();

    Carbon::setTestNow('2026-04-17 09:00:00');

    Livewire::actingAs($coach)
        ->test(CalendarIndex::class, ['view' => 'overview'])
        ->assertSet('preset', CalendarDateService::PRESET_NEXT_3_MONTHS)
        ->assertSet('start', '')
        ->assertSet('end', '');

    Carbon::setTestNow();
});

it('applies nested preset updates immediately', function () {
    $coach = User::factory()->coach()->create();

    Carbon::setTestNow('2026-04-17 09:00:00');

    Livewire::actingAs($coach)
        ->test(CalendarIndex::class, ['view' => 'overview'])
        ->set('range.preset', CalendarDateService::PRESET_NEXT_3_MONTHS)
        ->assertSet('preset', CalendarDateService::PRESET_NEXT_3_MONTHS)
        ->assertSet('start', '')
        ->assertSet('end', '');

    expect($coach->fresh()->config->get('calendar.settings'))->toMatchArray([
        'preset' => CalendarDateService::PRESET_NEXT_3_MONTHS,
        'start' => null,
        'end' => null,
    ]);

    Carbon::setTestNow();
});

it('persists fixed custom date ranges for all calendar views', function () {
    $coach = User::factory()->coach()->create();

    Livewire::actingAs($coach)
        ->test(CalendarIndex::class, ['view' => 'overview'])
        ->set('range', [
            'start' => '2026-04-13',
            'end' => '2026-05-17',
        ])
        ->assertSet('preset', CalendarDateService::PRESET_CUSTOM)
        ->assertSet('start', '2026-04-13')
        ->assertSet('end', '2026-05-17');

    expect($coach->fresh()->config->get('calendar.settings'))->toMatchArray([
        'preset' => CalendarDateService::PRESET_CUSTOM,
        'start' => '2026-04-13',
        'end' => '2026-05-17',
    ]);

    Livewire::actingAs($coach)
        ->test(CalendarIndex::class, ['view' => 'schedule'])
        ->assertSet('preset', CalendarDateService::PRESET_CUSTOM)
        ->assertSet('start', '2026-04-13')
        ->assertSet('end', '2026-05-17');
});

it('clamps custom calendar ranges to at most six months on the backend', function () {
    $coach = User::factory()->coach()->create();

    Livewire::actingAs($coach)
        ->test(CalendarIndex::class, ['view' => 'overview'])
        ->set('range', [
            'start' => '2026-01-01',
            'end' => '2026-12-31',
        ])
        ->assertSet('start', '2026-01-01')
        ->assertSet('end', '2026-06-30');

    expect($coach->fresh()->config->get('calendar.settings'))->toMatchArray([
        'preset' => CalendarDateService::PRESET_CUSTOM,
        'start' => '2026-01-01',
        'end' => '2026-06-30',
    ]);
});
