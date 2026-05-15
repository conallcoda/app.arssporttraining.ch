<?php

use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the athlete groups index route without colliding with athlete details', function () {
    $coach = User::factory()->coach()->create();

    $this->actingAs($coach)
        ->get(route('athlete-group-index'))
        ->assertOk()
        ->assertSee('Athlete Groups')
        ->assertSee('Search groups...');
});
