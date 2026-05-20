<?php

use App\Livewire\Database\CoachList;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('saves coach contact details from the list form', function () {
    $admin = User::factory()->admin()->create();
    $coach = User::factory()->coach()->create([
        'email' => null,
        'phone' => null,
    ]);

    Livewire::actingAs($admin)
        ->test(CoachList::class)
        ->call('handleFormSubmitted', [
            'id' => $coach->id,
            'forename' => $coach->forename,
            'surname' => $coach->surname,
            'email' => 'coach-updated@example.com',
            'phone' => '+41790003344',
            'color' => $coach->color,
            'name' => $coach->name,
        ]);

    expect($coach->fresh())
        ->email->toBe('coach-updated@example.com')
        ->phone->toBe('+41790003344');
});
