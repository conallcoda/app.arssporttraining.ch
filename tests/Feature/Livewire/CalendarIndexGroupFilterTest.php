<?php

use App\Livewire\Training\CalendarIndex;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('defaults to my groups when the coach owns at least one group', function () {
    $coach = User::factory()->coach()->create();
    UserGroup::create(['name' => 'Owned Group', 'owner_id' => $coach->id]);
    UserGroup::create(['name' => 'Other Group']);

    Livewire::actingAs($coach)
        ->test(CalendarIndex::class)
        ->assertSet('groupFilter', 'mine');
});

it('defaults to all groups when the coach owns no groups', function () {
    $coach = User::factory()->coach()->create();
    UserGroup::create(['name' => 'Shared Group']);

    Livewire::actingAs($coach)
        ->test(CalendarIndex::class)
        ->assertSet('groupFilter', 'all');
});

it('repopulates the group options when the filter changes and clears an invalid selection', function () {
    $coach = User::factory()->coach()->create();
    $ownedGroup = UserGroup::create(['name' => 'Owned Group', 'owner_id' => $coach->id]);
    $sharedGroup = UserGroup::create(['name' => 'Shared Group']);

    $component = Livewire::actingAs($coach)
        ->test(CalendarIndex::class, [
            'groupFilter' => 'all',
            'group' => (string) $sharedGroup->id,
        ]);

    expect($component->instance()->groupOptions->keys()->all())
        ->toBe([$ownedGroup->id, $sharedGroup->id]);

    $component
        ->set('groupFilter', 'mine')
        ->assertSet('group', '')
        ->assertSet('user', '')
        ->assertSet('view', 'overview');

    expect($component->instance()->groupOptions->keys()->all())
        ->toBe([$ownedGroup->id]);
});

it('allows clearing the group selection when the sidebar is disabled', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.calendar_sidebar.enabled', false);
    $coach->save();

    $group = UserGroup::create(['name' => 'Owned Group', 'owner_id' => $coach->id]);

    Livewire::actingAs($coach)
        ->test(CalendarIndex::class, [
            'group' => (string) $group->id,
            'view' => 'schedule',
        ])
        ->set('group', '')
        ->assertSet('group', '')
        ->assertSet('user', '')
        ->assertSet('view', 'overview');
});

it('keeps the add program modal mounted for the empty group state', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Owned Group', 'owner_id' => $coach->id]);

    $html = Livewire::actingAs($coach)
        ->test(CalendarIndex::class, [
            'group' => (string) $group->id,
        ])
        ->html();

    expect($html)->toContain('calendar_program_selection');
});
