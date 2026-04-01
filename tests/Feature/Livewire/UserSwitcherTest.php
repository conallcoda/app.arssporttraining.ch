<?php

use App\Livewire\UserSwitcher;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

it('renders when user switching is enabled', function () {
    config()->set('app.user_switching', true);

    $user = User::factory()->admin()->create();

    Livewire::actingAs($user)
        ->test(UserSwitcher::class)
        ->assertSuccessful()
        ->assertSee($user->name);
});

it('shows users grouped by type', function () {
    config()->set('app.user_switching', true);

    $admin = User::factory()->admin()->create();
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();

    Livewire::actingAs($admin)
        ->test(UserSwitcher::class)
        ->assertSee($admin->name)
        ->assertSee($coach->name)
        ->assertSee($athlete->name);
});

it('switches to another user when enabled', function () {
    config()->set('app.user_switching', true);

    $admin = User::factory()->admin()->create();
    $coach = User::factory()->coach()->create();

    Livewire::actingAs($admin)
        ->test(UserSwitcher::class)
        ->call('switchUser', $coach->id)
        ->assertRedirect();

    expect(auth()->id())->toBe($coach->id);
});

it('aborts with 403 when switching is disabled', function () {
    config()->set('app.user_switching', false);

    $admin = User::factory()->admin()->create();
    $coach = User::factory()->coach()->create();

    Livewire::actingAs($admin)
        ->test(UserSwitcher::class)
        ->call('switchUser', $coach->id)
        ->assertForbidden();
});

it('throws when target user does not exist', function () {
    config()->set('app.user_switching', true);

    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(UserSwitcher::class)
        ->call('switchUser', 99999);
})->throws(ModelNotFoundException::class);
