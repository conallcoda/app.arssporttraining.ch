<?php

use App\Data\Training\Calendar\CalendarSettingsData;
use App\Livewire\Training\CalendarProgramsView;
use App\Livewire\Training\CalendarScheduleView;
use App\Models\Users\User;
use Carbon\Carbon;
use Livewire\Livewire;

function weekSlotSettings(string $date = '2026-03-02'): CalendarSettingsData
{
    return new CalendarSettingsData(
        start: $date,
        end: Carbon::parse($date)->addDays(6)->format('Y-m-d'),
        preset: null,
    );
}

it('dispatches create and edit slot payloads from the schedule view', function () {
    $coach = User::factory()->coach()->create();
    $weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);

    $component = Livewire::actingAs($coach)
        ->test(CalendarScheduleView::class, [
            'groupId' => 4,
            'userId' => 8,
            'calendarSettings' => weekSlotSettings(),
            'weekStartsOn' => $weekStartsOn,
        ]);

    $component->call('openWeekSlot', '2026-03-10', 'pm')
        ->assertDispatched('open-week-slot', data: [
            'date' => '2026-03-10',
            'start_time' => '14:00',
            'groupId' => 4,
            'userId' => 8,
        ]);

    $component->call('editWeekSlot', 12, '2026-03-10', '09:30')
        ->assertDispatched('open-week-slot', data: [
            'date' => '2026-03-10',
            'start_time' => '09:30',
            'training_program_id' => 12,
            'groupId' => 4,
            'userId' => 8,
        ]);
});

it('dispatches prefilled slot payloads from the programs view', function () {
    $coach = User::factory()->coach()->create();
    $weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);

    Livewire::actingAs($coach)
        ->test(CalendarProgramsView::class, [
            'groupId' => 4,
            'userId' => 8,
            'calendarSettings' => weekSlotSettings(),
            'weekStartsOn' => $weekStartsOn,
            'weekEndsOn' => ($weekStartsOn + 6) % 7,
        ])
        ->call('openProgramSlot', 12, '2026-03-10')
        ->assertDispatched('open-week-slot', data: [
            'date' => '2026-03-10',
            'start_time' => '09:00',
            'training_program_id' => 12,
            'groupId' => 4,
            'userId' => null,
            'prefill' => true,
            'preselectedUserId' => 8,
        ]);
});
