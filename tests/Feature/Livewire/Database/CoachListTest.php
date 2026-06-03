<?php

use App\Livewire\Database\CoachList;
use App\Livewire\Database\OwnerList;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('saves coach contact details from the list form', function () {
    $owner = User::factory()->coach()->create(['owner_id' => null]);
    $coach = User::factory()->coach()->create([
        'owner_id' => $owner->id,
        'email' => null,
        'phone' => null,
    ]);

    Livewire::actingAs($owner)
        ->test(CoachList::class)
        ->call('handleFormSubmitted', [
            'id' => $coach->id,
            'forename' => $coach->forename,
            'surname' => $coach->surname,
            'email' => 'coach-updated@example.com',
            'phone' => '+41790003344',
            'color' => $coach->color,
            'owner_id' => $owner->id,
            'name' => $coach->name,
        ]);

    expect($coach->fresh())
        ->email->toBe('coach-updated@example.com')
        ->phone->toBe('+41790003344');
});

it('saves a coach owner from the list form', function () {
    $signedInOwner = User::factory()->coach()->create(['owner_id' => null]);
    $otherOwner = User::factory()->coach()->create(['owner_id' => null]);
    $coach = User::factory()->coach()->create(['owner_id' => $signedInOwner->id]);

    Livewire::actingAs($signedInOwner)
        ->test(CoachList::class)
        ->call('handleFormSubmitted', [
            'id' => $coach->id,
            'forename' => $coach->forename,
            'surname' => $coach->surname,
            'email' => $coach->email,
            'phone' => $coach->phone,
            'color' => $coach->color,
            'owner_id' => $otherOwner->id,
            'name' => $coach->name,
        ]);

    expect($coach->fresh()->owner_id)->toBe($otherOwner->id);
});

it('creates coaches with the signed in owner preselected', function () {
    $owner = User::factory()->coach()->create(['owner_id' => null]);

    Livewire::actingAs($owner)
        ->test(CoachList::class)
        ->call('handleFormSubmitted', [
            'id' => null,
            'forename' => 'New',
            'surname' => 'Coach',
            'email' => 'new-coach@example.com',
            'phone' => null,
            'color' => null,
            'owner_id' => $owner->id,
            'name' => 'New Coach',
        ]);

    expect(User::query()
        ->where('email', 'new-coach@example.com')
        ->firstOrFail()
        ->owner_id)->toBe($owner->id);
});

it('shows my coaches and all owned coaches in separate tabs', function () {
    $owner = User::factory()->coach()->create([
        'forename' => 'Owner',
        'surname' => 'Coach',
        'owner_id' => null,
    ]);

    $myCoach = User::factory()->coach()->create([
        'forename' => 'My',
        'surname' => 'Coach',
        'owner_id' => $owner->id,
    ]);

    $otherAdmin = User::factory()->coach()->create([
        'forename' => 'Other',
        'surname' => 'Admin',
        'owner_id' => null,
    ]);

    $otherCoach = User::factory()->coach()->create([
        'forename' => 'Other',
        'surname' => 'Coach',
        'owner_id' => $otherAdmin->id,
    ]);

    $component = Livewire::actingAs($owner)
        ->test(CoachList::class)
        ->set('selectedTab', 'mine');

    expect($component->instance()->items->pluck('id')->all())->toBe([$myCoach->id]);

    $component->set('selectedTab', 'all');

    expect($component->instance()->items->pluck('id')->sort()->values()->all())
        ->toBe(collect([$myCoach->id, $otherCoach->id])->sort()->values()->all());
});

it('filters coaches by admin owner', function () {
    $signedInAdmin = User::factory()->coach()->create(['owner_id' => null]);
    $adams = User::factory()->coach()->create(['forename' => 'Amy', 'surname' => 'Adams', 'owner_id' => null]);
    $smith = User::factory()->coach()->create(['forename' => 'Sam', 'surname' => 'Smith', 'owner_id' => null]);

    $adamsCoach = User::factory()->coach()->create([
        'forename' => 'Coach',
        'surname' => 'Adams',
        'owner_id' => $adams->id,
    ]);

    User::factory()->coach()->create([
        'forename' => 'Coach',
        'surname' => 'Smith',
        'owner_id' => $smith->id,
    ]);

    $component = Livewire::actingAs($signedInAdmin)
        ->test(CoachList::class)
        ->set('selectedTab', 'all')
        ->set('filters', ['owner' => [$adams->id]]);

    expect($component->instance()->items->pluck('id')->all())->toBe([$adamsCoach->id]);
});

it('shows owners separately from owned coaches', function () {
    $signedInOwner = User::factory()->coach()->create(['owner_id' => null]);
    $otherOwner = User::factory()->coach()->create(['owner_id' => null]);
    $ownedCoach = User::factory()->coach()->create(['owner_id' => $signedInOwner->id]);

    $component = Livewire::actingAs($signedInOwner)
        ->test(OwnerList::class);

    expect($component->instance()->items->pluck('id')->sort()->values()->all())
        ->toBe(collect([$signedInOwner->id, $otherOwner->id])->sort()->values()->all())
        ->and($component->instance()->items->pluck('id')->contains($ownedCoach->id))->toBeFalse();
});

it('creates owners without an owner id', function () {
    $signedInOwner = User::factory()->coach()->create(['owner_id' => null]);

    Livewire::actingAs($signedInOwner)
        ->test(OwnerList::class)
        ->call('handleFormSubmitted', [
            'id' => null,
            'forename' => 'New',
            'surname' => 'Owner',
            'email' => 'new-owner@example.com',
            'phone' => null,
            'color' => null,
            'name' => 'New Owner',
        ]);

    expect(User::query()
        ->where('email', 'new-owner@example.com')
        ->firstOrFail()
        ->owner_id)->toBeNull();
});
