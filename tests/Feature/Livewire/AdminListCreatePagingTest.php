<?php

use App\Livewire\Database\AthleteList;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('resolves the created athlete page within the active ownership tab', function () {
    $coach = User::factory()->coach()->create();
    $otherCoach = User::factory()->coach()->create();

    User::factory()->count(9)->athlete()->create([
        'owner_id' => $coach->id,
    ]);

    User::factory()->count(35)->athlete()->create([
        'owner_id' => $otherCoach->id,
    ]);

    $component = Livewire::actingAs($coach)
        ->test(AthleteList::class)
        ->set('selectedTab', 'mine');

    $component->call('handleFormSubmitted', [
        'forename' => 'New',
        'surname' => 'Athlete',
        'owner_id' => $coach->id,
        'internalTags' => [],
    ]);

    expect($component->instance()->items->currentPage())->toBe(1)
        ->and($component->instance()->items->count())->toBe(10)
        ->and($component->instance()->items->pluck('owner_id')->unique()->all())->toBe([$coach->id]);
});
