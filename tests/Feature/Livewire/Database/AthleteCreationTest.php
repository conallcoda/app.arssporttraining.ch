<?php

use App\Data\Athlete\AthleteData;
use App\Livewire\Database\AthleteList;
use App\Models\Users\User;
use Coda\Cms\Livewire\FormModal;
use Livewire\Livewire;

it('creates an athlete through the add modal with only required fields', function () {
    $coach = User::factory()->coach()->create();

    Livewire::actingAs($coach)
        ->test(FormModal::class, [
            'name' => 'add-athlete',
            'title' => 'Add Athlete',
            'formDataClass' => AthleteData::class,
            'persistOnSubmit' => true,
        ])
        ->call('open')
        ->set('data.forename', 'New')
        ->set('data.surname', 'Athlete')
        ->call('submit')
        ->assertHasNoErrors();

    expect(User::query()->where('forename', 'New')->where('surname', 'Athlete')->exists())->toBeTrue();
});

it('accepts nullable display fields passed by the add modal', function () {
    $coach = User::factory()->coach()->create();

    Livewire::actingAs($coach)
        ->test(AthleteList::class)
        ->call('handleFormSubmitted', [
            'forename' => 'Another',
            'surname' => 'Athlete',
            'internalTags' => [],
            'personName' => null,
            'setupStatus' => null,
            'setupStatusLabel' => null,
        ])
        ->assertHasNoErrors();

    expect(User::query()->where('forename', 'Another')->where('surname', 'Athlete')->exists())->toBeTrue();
});
