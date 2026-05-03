<?php

use App\Data\Coach\Settings\CalendarSidebarSetting;
use App\Data\Coach\Settings\SessionGroupingSetting;
use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Livewire\CoachSettings;
use App\Models\Users\User;
use Livewire\Livewire;

it('loads persisted coach settings on mount', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.'.CalendarSidebarSetting::fieldsetKey(), [
        'enabled' => false,
    ]);
    $coach->config->set('settings.'.SessionGroupingSetting::fieldsetKey(), [
        'mode' => SessionGroupingMode::Groups->value,
        'groupSize' => 3,
    ]);
    $coach->save();

    Livewire::actingAs($coach)
        ->test(CoachSettings::class)
        ->assertSet('data.'.CalendarSidebarSetting::fieldsetKey().'.enabled', false)
        ->assertSet('data.'.SessionGroupingSetting::fieldsetKey().'.mode', SessionGroupingMode::Groups->value)
        ->assertSet('data.'.SessionGroupingSetting::fieldsetKey().'.groupSize', 3);
});

it('persists updated settings and dispatches a saved event', function () {
    $coach = User::factory()->coach()->create();

    Livewire::actingAs($coach)
        ->test(CoachSettings::class)
        ->set('data.'.CalendarSidebarSetting::fieldsetKey().'.enabled', false)
        ->set('data.'.SessionGroupingSetting::fieldsetKey().'.mode', SessionGroupingMode::Groups->value)
        ->set('data.'.SessionGroupingSetting::fieldsetKey().'.groupSize', 6)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('coach-settings-saved');

    expect($coach->fresh()->config->get('settings.'.CalendarSidebarSetting::fieldsetKey()))
        ->toMatchArray(['enabled' => false]);

    expect($coach->fresh()->config->get('settings.'.SessionGroupingSetting::fieldsetKey()))
        ->toMatchArray([
            'mode' => SessionGroupingMode::Groups->value,
            'groupSize' => 6,
        ]);
});
