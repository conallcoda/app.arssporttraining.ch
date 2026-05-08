<?php

use App\Livewire\Athlete\Calendar;
use App\Models\Users\User;

it('loads the train dashboard from a route date segment', function () {
    config()->set('athlete.dashboard_today_override', '03.04.2026');

    $athlete = User::factory()->athlete()->create();

    $this->actingAs($athlete)
        ->get('/dashboard/train/2026-04-03')
        ->assertOk()
        ->assertSeeLivewire(Calendar::class)
        ->assertSee('/dashboard/train/2026-04-03', false)
        ->assertSee('/dashboard/schedule/2026-03-30', false)
        ->assertSee('Train')
        ->assertSee('Schedule')
        ->assertSee('Unrecorded');
});

it('loads the schedule dashboard from a route date segment', function () {
    config()->set('athlete.dashboard_today_override', '03.04.2026');

    $athlete = User::factory()->athlete()->create();

    $this->actingAs($athlete)
        ->get('/dashboard/schedule/2026-03-30')
        ->assertOk()
        ->assertSeeLivewire(Calendar::class)
        ->assertSee('/dashboard/schedule/2026-03-30', false)
        ->assertSee('/dashboard/train/2026-04-03', false);
});

it('redirects schedule urls to the start of the week', function () {
    $athlete = User::factory()->athlete()->create();

    $this->actingAs($athlete)
        ->get('/dashboard/schedule/2026-04-03')
        ->assertRedirect('/dashboard/schedule/2026-03-30');
});

it('redirects legacy query-string calendar urls to the train route', function () {
    $athlete = User::factory()->athlete()->create();

    $this->actingAs($athlete)
        ->get('/dashboard/calendar?date=2026-04-03')
        ->assertRedirect('/dashboard/train/2026-04-03');
});

it('redirects legacy day calendar urls to the train route', function () {
    $athlete = User::factory()->athlete()->create();

    $this->actingAs($athlete)
        ->get('/dashboard/calendar/day/2026-04-03')
        ->assertRedirect('/dashboard/train/2026-04-03');
});

it('redirects legacy week calendar urls to the schedule route', function () {
    $athlete = User::factory()->athlete()->create();

    $this->actingAs($athlete)
        ->get('/dashboard/calendar/week/2026-04-03')
        ->assertRedirect('/dashboard/schedule/2026-03-30');
});

it('redirects the dashboard root to the train route', function () {
    $athlete = User::factory()->athlete()->create();

    $this->actingAs($athlete)
        ->get('/dashboard')
        ->assertRedirect('/dashboard/train');
});

it('ignores persisted session dates and defaults train and schedule from today', function () {
    config()->set('athlete.dashboard_today_override', '03.04.2026');

    $athlete = User::factory()->athlete()->create();

    $this->actingAs($athlete)
        ->withSession([
            'athlete.dashboard.train_date' => '2026-05-01',
            'athlete.dashboard.schedule_date' => '2026-05-04',
        ])
        ->get('/dashboard/train')
        ->assertOk()
        ->assertSee('/dashboard/train/2026-04-03', false)
        ->assertSee('/dashboard/schedule/2026-03-30', false)
        ->assertDontSee('/dashboard/train/2026-05-01', false)
        ->assertDontSee('/dashboard/schedule/2026-05-04', false);
});
