<?php

use App\Livewire\UserGroupSidebar;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Livewire\Livewire;

it('defaults the group filter to mine when the coach owns groups', function () {
    $coach = User::factory()->coach()->create();
    UserGroup::create(['name' => 'Owned Group', 'owner_id' => $coach->id]);
    UserGroup::create(['name' => 'Shared Group']);

    Livewire::actingAs($coach)
        ->test(UserGroupSidebar::class, ['showGroupFilter' => true])
        ->assertSet('groupFilter', 'mine');
});

it('defaults the group filter to all when the coach owns no groups', function () {
    $coach = User::factory()->coach()->create();
    UserGroup::create(['name' => 'Shared Group']);

    Livewire::actingAs($coach)
        ->test(UserGroupSidebar::class, ['showGroupFilter' => true])
        ->assertSet('groupFilter', 'all');
});

it('filters visible groups by ownership and search matches member names', function () {
    $coach = User::factory()->coach()->create();
    $matchingAthlete = User::factory()->athlete()->create(['forename' => 'Alicia', 'surname' => 'Stone']);
    $otherAthlete = User::factory()->athlete()->create(['forename' => 'Bruno', 'surname' => 'Vale']);
    $ownedGroup = UserGroup::create(['name' => 'Owned Group', 'owner_id' => $coach->id]);
    $sharedGroup = UserGroup::create(['name' => 'Shared Group']);
    $ownedGroup->members()->attach($matchingAthlete);
    $sharedGroup->members()->attach($otherAthlete);

    $component = Livewire::actingAs($coach)
        ->test(UserGroupSidebar::class, ['showGroupFilter' => true]);

    expect($component->instance()->groups->pluck('name')->all())
        ->toBe(['Owned Group']);

    $component->set('groupFilter', 'all')
        ->set('search', 'alici');

    expect($component->instance()->groups->pluck('name')->all())
        ->toBe(['Owned Group']);

    expect($component->instance()->hasMatchingMember($ownedGroup->fresh('members')))->toBeTrue()
        ->and($component->instance()->isMemberMatch($matchingAthlete->fresh()))->toBeTrue()
        ->and($component->instance()->isMemberMatch($otherAthlete->fresh()))->toBeFalse();
});

it('toggles single-athlete selections and dispatches selection changes', function () {
    $coach = User::factory()->coach()->create();

    $component = Livewire::actingAs($coach)
        ->test(UserGroupSidebar::class, ['mode' => 'single-athlete']);

    $component->call('selectUser', 10, 15)
        ->assertSet('selected', [['group' => 10, 'user' => 15]])
        ->assertDispatched('sidebar-selection-changed', selected: [['group' => 10, 'user' => 15]]);

    expect($component->instance()->isSelected(10, 15))->toBeTrue()
        ->and($component->instance()->hasSelectionInGroup(10))->toBeTrue();

    $component->call('selectUser', 10, 15)
        ->assertSet('selected', [])
        ->assertDispatched('sidebar-selection-changed', selected: []);
});

it('adds and removes users in multi-select mode', function () {
    $coach = User::factory()->coach()->create();

    $component = Livewire::actingAs($coach)
        ->test(UserGroupSidebar::class, ['mode' => 'multiple-athletes']);

    $component->call('selectUser', 1, 100)
        ->call('selectUser', 1, 101)
        ->assertSet('selected', [
            ['group' => 1, 'user' => 100],
            ['group' => 1, 'user' => 101],
        ]);

    $component->call('selectUser', 1, 100)
        ->assertSet('selected', [
            ['group' => 1, 'user' => 101],
        ]);
});

it('ignores user selection in single-group mode and toggles groups instead', function () {
    $coach = User::factory()->coach()->create();

    $component = Livewire::actingAs($coach)
        ->test(UserGroupSidebar::class, ['mode' => 'single-group', 'navigateEvent' => 'go-to-group']);

    $component->call('selectUser', 5, 50)
        ->assertSet('selected', []);

    $component->call('selectGroup', 5)
        ->assertSet('selected', [['group' => 5]])
        ->assertDispatched('sidebar-selection-changed', selected: [['group' => 5]]);

    expect($component->instance()->isGroupSelected(5))->toBeTrue();

    $component->call('navigate', 'group', 5)
        ->assertDispatched('go-to-group', type: 'group', group: 5, user: null);

    $component->dispatch('overview-selection', selected: [['group' => 9, 'user' => 99]])
        ->assertSet('selected', [['group' => 9, 'user' => 99]]);
});

it('dispatches group filter changes when the filter is updated', function () {
    $coach = User::factory()->coach()->create();
    UserGroup::create(['name' => 'Owned Group', 'owner_id' => $coach->id]);

    Livewire::actingAs($coach)
        ->test(UserGroupSidebar::class, ['showGroupFilter' => true])
        ->set('groupFilter', 'all')
        ->assertDispatched('group-filter-changed', filter: 'all');
});
