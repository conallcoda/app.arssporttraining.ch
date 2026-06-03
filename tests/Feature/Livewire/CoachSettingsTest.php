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
        'copyValuesAutomatically' => false,
    ]);
    $coach->save();

    Livewire::actingAs($coach)
        ->test(CoachSettings::class)
        ->assertSet('data.'.CalendarSidebarSetting::fieldsetKey().'.enabled', false)
        ->assertSet('data.'.SessionGroupingSetting::fieldsetKey().'.mode', SessionGroupingMode::Groups->value)
        ->assertSet('data.'.SessionGroupingSetting::fieldsetKey().'.groupSize', 3)
        ->assertSet('data.'.SessionGroupingSetting::fieldsetKey().'.copyValuesAutomatically', false);
});

it('defaults coach session grouping settings to groups of two when unset', function () {
    $coach = User::factory()->coach()->create();

    Livewire::actingAs($coach)
        ->test(CoachSettings::class)
        ->assertSet('data.'.SessionGroupingSetting::fieldsetKey().'.mode', SessionGroupingMode::Groups->value)
        ->assertSet('data.'.SessionGroupingSetting::fieldsetKey().'.groupSize', 2)
        ->assertSet('data.'.SessionGroupingSetting::fieldsetKey().'.copyValuesAutomatically', true);
});

it('persists updated settings and dispatches a saved event', function () {
    $coach = User::factory()->coach()->create();

    Livewire::actingAs($coach)
        ->test(CoachSettings::class)
        ->set('data.'.CalendarSidebarSetting::fieldsetKey().'.enabled', false)
        ->set('data.'.SessionGroupingSetting::fieldsetKey().'.mode', SessionGroupingMode::Groups->value)
        ->set('data.'.SessionGroupingSetting::fieldsetKey().'.groupSize', 6)
        ->set('data.'.SessionGroupingSetting::fieldsetKey().'.copyValuesAutomatically', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('coach-settings-saved');

    expect($coach->fresh()->config->get('settings.'.CalendarSidebarSetting::fieldsetKey()))
        ->toMatchArray(['enabled' => false]);

    expect($coach->fresh()->config->get('settings.'.SessionGroupingSetting::fieldsetKey()))
        ->toMatchArray([
            'mode' => SessionGroupingMode::Groups->value,
            'groupSize' => 6,
            'copyValuesAutomatically' => false,
        ]);
});

it('normalizes none grouping to a group size of one when saving coach settings', function () {
    $coach = User::factory()->coach()->create();

    Livewire::actingAs($coach)
        ->test(CoachSettings::class)
        ->set('data.'.SessionGroupingSetting::fieldsetKey().'.mode', SessionGroupingMode::None->value)
        ->set('data.'.SessionGroupingSetting::fieldsetKey().'.groupSize', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($coach->fresh()->config->get('settings.'.SessionGroupingSetting::fieldsetKey()))
        ->toMatchArray([
            'mode' => SessionGroupingMode::None->value,
            'groupSize' => 1,
            'copyValuesAutomatically' => false,
        ]);
});

it('validates empty coach session grouping group size before hydration', function () {
    $coach = User::factory()->coach()->create();

    Livewire::actingAs($coach)
        ->test(CoachSettings::class)
        ->set('data.'.SessionGroupingSetting::fieldsetKey().'.mode', SessionGroupingMode::Groups->value)
        ->set('data.'.SessionGroupingSetting::fieldsetKey().'.groupSize', '')
        ->call('save')
        ->assertHasErrors([
            'data.'.SessionGroupingSetting::fieldsetKey().'.groupSize' => 'required',
        ]);
});

it('normalizes grouped coach settings to a minimum size of two when saving', function () {
    $coach = User::factory()->coach()->create();

    Livewire::actingAs($coach)
        ->test(CoachSettings::class)
        ->set('data.'.SessionGroupingSetting::fieldsetKey().'.mode', SessionGroupingMode::Groups->value)
        ->set('data.'.SessionGroupingSetting::fieldsetKey().'.groupSize', 1)
        ->call('save')
        ->assertHasNoErrors();

    expect($coach->fresh()->config->get('settings.'.SessionGroupingSetting::fieldsetKey()))
        ->toMatchArray([
            'mode' => SessionGroupingMode::Groups->value,
            'groupSize' => 2,
            'copyValuesAutomatically' => true,
        ]);
});

it('defaults week coach settings to a size of one when switching modes', function () {
    $coach = User::factory()->coach()->create();

    Livewire::actingAs($coach)
        ->test(CoachSettings::class)
        ->set('data.'.SessionGroupingSetting::fieldsetKey().'.mode', SessionGroupingMode::Week->value)
        ->assertSet('data.'.SessionGroupingSetting::fieldsetKey().'.groupSize', 1)
        ->call('save')
        ->assertHasNoErrors();

    expect($coach->fresh()->config->get('settings.'.SessionGroupingSetting::fieldsetKey()))
        ->toMatchArray([
            'mode' => SessionGroupingMode::Week->value,
            'groupSize' => 1,
            'copyValuesAutomatically' => true,
        ]);
});
