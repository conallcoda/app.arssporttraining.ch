<?php

use App\Livewire\Training\CalendarRangeForm;
use Livewire\Livewire;

it('opens with provided data and initializes state', function () {
    Livewire::test(CalendarRangeForm::class)
        ->call('open', data: [
            'mode' => 'week',
            'date' => '2026-03-02',
            'start' => '',
            'end' => '',
        ])
        ->assertSet('data.mode', 'week')
        ->assertSet('data.date', '2026-03-02')
        ->assertSet('openCount', 1);
});

it('dispatches calendar-range.submitted on submit', function () {
    Livewire::test(CalendarRangeForm::class)
        ->call('open', data: [
            'mode' => 'month',
            'date' => '2026-03-01',
            'start' => '',
            'end' => '',
        ])
        ->call('submit')
        ->assertDispatched('calendar-range.submitted');
});

it('submits with correct data payload', function () {
    Livewire::test(CalendarRangeForm::class)
        ->call('open', data: [
            'mode' => 'range',
            'date' => '2026-03-01',
            'start' => '2026-02-23',
            'end' => '2026-04-05',
        ])
        ->call('submit')
        ->assertDispatched('calendar-range.submitted', fn (string $event, array $params) => $params['data']['mode'] === 'range'
            && $params['data']['start'] === '2026-02-23'
            && $params['data']['end'] === '2026-04-05');
});

it('normalizes month date to start of month', function () {
    Livewire::test(CalendarRangeForm::class)
        ->call('open', data: [
            'mode' => 'month',
            'date' => '2026-03-01',
            'start' => '',
            'end' => '',
        ])
        ->set('data.date', '2026-04-15')
        ->assertSet('data.date', '2026-04-01');
});

it('normalizes week date to start of week', function () {
    Livewire::test(CalendarRangeForm::class)
        ->call('open', data: [
            'mode' => 'week',
            'date' => '2026-03-02',
            'start' => '',
            'end' => '',
        ])
        ->set('data.date', '2026-03-05')
        ->assertSet('data.date', '2026-03-02');
});

it('normalizes range start to start of week', function () {
    Livewire::test(CalendarRangeForm::class)
        ->call('open', data: [
            'mode' => 'range',
            'date' => '2026-03-01',
            'start' => '',
            'end' => '',
        ])
        ->set('data.start', '2026-03-04')
        ->assertSet('data.start', '2026-03-02');
});

it('normalizes range end to end of week', function () {
    Livewire::test(CalendarRangeForm::class)
        ->call('open', data: [
            'mode' => 'range',
            'date' => '2026-03-01',
            'start' => '',
            'end' => '',
        ])
        ->set('data.end', '2026-03-04')
        ->assertSet('data.end', '2026-03-08');
});

it('does not normalize day mode date', function () {
    Livewire::test(CalendarRangeForm::class)
        ->call('open', data: [
            'mode' => 'day',
            'date' => '2026-03-01',
            'start' => '',
            'end' => '',
        ])
        ->set('data.date', '2026-03-15')
        ->assertSet('data.date', '2026-03-15');
});
