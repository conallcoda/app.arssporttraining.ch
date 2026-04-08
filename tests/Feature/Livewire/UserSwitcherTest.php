<?php

use App\Models\Users\User;
use Coda\Cms\Livewire\UserSwitcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

it('renders when user switching is enabled', function () {
    config()->set('cms.user_switching', true);

    $user = User::factory()->admin()->create();

    Livewire::actingAs($user)
        ->test(UserSwitcher::class)
        ->assertSuccessful()
        ->assertSee($user->name);
});

it('shows users grouped by type', function () {
    config()->set('cms.user_switching', true);

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
    config()->set('cms.user_switching', true);

    $admin = User::factory()->admin()->create();
    $coach = User::factory()->coach()->create();

    Livewire::actingAs($admin)
        ->test(UserSwitcher::class)
        ->call('switchUser', $coach->id)
        ->assertRedirect();

    expect(auth()->id())->toBe($coach->id);
});

it('aborts with 403 when switching is disabled', function () {
    config()->set('cms.user_switching', false);

    $admin = User::factory()->admin()->create();
    $coach = User::factory()->coach()->create();

    Livewire::actingAs($admin)
        ->test(UserSwitcher::class)
        ->call('switchUser', $coach->id)
        ->assertForbidden();
});

it('redirects to home by type when switching to a different user type', function () {
    config()->set('cms.user_switching', true);
    config()->set('cms.home_by_type', [
        'athlete' => '/dashboard',
        '*' => '/admin/programs',
    ]);

    $admin = User::factory()->admin()->create();
    $athlete = User::factory()->athlete()->create();

    Livewire::actingAs($admin)
        ->test(UserSwitcher::class)
        ->call('switchUser', $athlete->id)
        ->assertRedirect('/dashboard');

    expect(auth()->id())->toBe($athlete->id);
});

it('redirects to referrer when switching to the same user type', function () {
    config()->set('cms.user_switching', true);
    config()->set('cms.home_by_type', [
        'admin' => '/admin/programs',
        '*' => '/admin/programs',
    ]);

    $admin1 = User::factory()->admin()->create();
    $admin2 = User::factory()->admin()->create();

    Livewire::actingAs($admin1)
        ->test(UserSwitcher::class)
        ->call('switchUser', $admin2->id)
        ->assertRedirect();

    expect(auth()->id())->toBe($admin2->id);
});

it('throws when target user does not exist', function () {
    config()->set('cms.user_switching', true);

    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(UserSwitcher::class)
        ->call('switchUser', 99999);
})->throws(ModelNotFoundException::class);
