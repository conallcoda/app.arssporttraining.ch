<?php

use App\Livewire\Athlete\Calendar;
use App\Models\Users\User;

it('loads the day calendar from a route date segment', function () {
    $athlete = User::factory()->athlete()->create();

    $this->actingAs($athlete)
        ->get('/dashboard/calendar/day/2026-04-03')
        ->assertOk()
        ->assertSeeLivewire(Calendar::class)
        ->assertSee('/dashboard/calendar/week/2026-04-03', false);
});

it('loads the week calendar from a route date segment', function () {
    $athlete = User::factory()->athlete()->create();

    $this->actingAs($athlete)
        ->get('/dashboard/calendar/week/2026-03-30')
        ->assertOk()
        ->assertSeeLivewire(Calendar::class)
        ->assertSee('/dashboard/calendar/day/2026-03-30', false);
});

it('redirects week calendar urls to the week start date', function () {
    $athlete = User::factory()->athlete()->create();

    $this->actingAs($athlete)
        ->get('/dashboard/calendar/week/2026-04-03')
        ->assertRedirect('/dashboard/calendar/week/2026-03-30');
});

it('redirects legacy query-string calendar urls to the day route', function () {
    $athlete = User::factory()->athlete()->create();

    $this->actingAs($athlete)
        ->get('/dashboard/calendar?date=2026-04-03')
        ->assertRedirect('/dashboard/calendar/day/2026-04-03');
});
